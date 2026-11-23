; NaviSuite-Server-Setup.exe: installs, upgrades and removes the NaviSuite
; vessel server on a ship's master PC.
;
; Built by deploy/build-installers.sh (do not compile by hand: the payload and
; the defines come from the build). The installer itself only unpacks the
; payload into <Root>\setup (administrators only) and runs
; release\deploy\windows\setup-server.ps1, which does the work, checks
; compatibility and writes the log. See docs/checkpoint-8-installers.md.
;
; Guided:  NaviSuite-Server-Setup.exe
; Silent:  NaviSuite-Server-Setup.exe /S /ANSWERS=D:\vessel-12.ini     (new server)
;          NaviSuite-Server-Setup.exe /S                               (upgrade or repair)
; Options: /ROOT=C:\NaviSuite   /ALLOWDOWNGRADE   /TESTBUILD (test builds only)
;
; Exit codes: 0 done; 1 failed or cancelled; 2 installed, but the health check
; found problems; 3 refused (this PC or the installed version is not
; compatible; nothing changed); 4 a newer version is installed; 5 missing or
; wrong options.

Unicode true
ManifestDPIAware true
RequestExecutionLevel admin
SetCompressor /SOLID lzma
SetCompressorDictSize 64

!include MUI2.nsh
!include LogicLib.nsh
!include nsDialogs.nsh
!include FileFunc.nsh
!include WordFunc.nsh
!include x64.nsh
!include WinVer.nsh

!ifndef NS_VERSION | NS_VERSION_QUAD | NS_RELEASE | NS_PAYLOAD | NS_OUTFILE | NS_TEST_BUILD
  !error "Build this installer with deploy/build-installers.sh."
!endif

!define REG_KEY "Software\NaviSuite\Server"
!define UNINST_KEY "Software\Microsoft\Windows\CurrentVersion\Uninstall\NaviSuiteServer"
!define UNINSTALLER "NaviSuite-Server-Uninstall.exe"

Name "NaviSuite Server ${NS_VERSION}"
OutFile "${NS_OUTFILE}"
InstallDir "C:\NaviSuite"
BrandingText "NaviSuite ${NS_VERSION} (${NS_RELEASE})"
ShowInstDetails show
ShowUninstDetails show
VIProductVersion "${NS_VERSION_QUAD}"
VIAddVersionKey "ProductName" "NaviSuite Server"
VIAddVersionKey "ProductVersion" "${NS_VERSION}"
VIAddVersionKey "FileVersion" "${NS_VERSION}"
VIAddVersionKey "FileDescription" "NaviSuite vessel server setup"
VIAddVersionKey "CompanyName" "NaviSuite"
VIAddVersionKey "LegalCopyright" "NaviSuite"
VIAddVersionKey "Comments" "Release ${NS_RELEASE}"

Var Mode            ; install | upgrade
Var InstalledVersion
Var Args
Var AnswerFile
Var PayloadDir
Var ExitCode
Var FinishText
Var ClientKit
Var RemoveData
; settings page
Var VesselId
Var ServerName
Var CloudUrl
Var NodeId
Var SyncToken
Var Firewall
Var hVesselId
Var hServerName
Var hCloudUrl
Var hNodeId
Var hSyncToken
Var hFirewall

; ------------------------------------------------------------------ log
; <Root>\setup\installer.log: one line per step of every run. The detailed
; log of the work is written by setup-server.ps1 to data\logs\setup.
!macro Log TEXT
  Push $9
  CreateDirectory "$INSTDIR\setup"
  ClearErrors
  FileOpen $9 "$INSTDIR\setup\installer.log" a
  ${IfNot} ${Errors}
    FileSeek $9 0 END
    FileWrite $9 "${TEXT}$\r$\n"
    FileClose $9
  ${EndIf}
  Pop $9
!macroend

!macro Refuse CODE TEXT
  ${IfNot} ${Silent}
    MessageBox MB_ICONSTOP|MB_OK "${TEXT}"
  ${EndIf}
  SetErrorLevel ${CODE}
  Quit
!macroend

; "1.2.3-test.abc" -> "1.2.3"
!macro VersionCore IN OUT
  Push $R8
  Push $R9
  StrCpy $R8 0
  ${Do}
    StrCpy $R9 "${IN}" 1 $R8
    ${If} $R9 == ""
    ${OrIf} $R9 == "-"
    ${OrIf} $R9 == "+"
      ${ExitDo}
    ${EndIf}
    IntOp $R8 $R8 + 1
  ${Loop}
  StrCpy ${OUT} "${IN}" $R8
  Pop $R9
  Pop $R8
!macroend

; ------------------------------------------------------------------ pages
!define MUI_ABORTWARNING
!define MUI_ICON "${NSISDIR}\Contrib\Graphics\Icons\modern-install.ico"
!define MUI_UNICON "${NSISDIR}\Contrib\Graphics\Icons\modern-uninstall.ico"

!if ${NS_TEST_BUILD} == 1
  !define MUI_WELCOMEPAGE_TITLE "TEST BUILD of NaviSuite Server ${NS_VERSION}"
  !define MUI_WELCOMEPAGE_TEXT "This installer did not pass the test suite gate. Use it on a test PC only, never on a vessel.$\r$\n$\r$\nRelease ${NS_RELEASE}."
!else
  !define MUI_WELCOMEPAGE_TEXT "This sets up this PC as the ship's NaviSuite server: the database, the web server for the crew PCs, the background services, synchronisation with the office and the nightly backups.$\r$\n$\r$\nAn existing NaviSuite server is upgraded: its settings, credentials, database and files are kept, and a database backup is taken first.$\r$\n$\r$\nRelease ${NS_RELEASE}."
!endif
!insertmacro MUI_PAGE_WELCOME
!define MUI_PAGE_CUSTOMFUNCTION_PRE SkipUnlessNew
!define MUI_DIRECTORYPAGE_TEXT_TOP "NaviSuite keeps its programs, database, uploaded files and backups in this folder. The path must not contain spaces."
!insertmacro MUI_PAGE_DIRECTORY
Page custom SettingsPage SettingsLeave
!insertmacro MUI_PAGE_INSTFILES
!define MUI_FINISHPAGE_TITLE "NaviSuite Server ${NS_VERSION}"
!define MUI_FINISHPAGE_TEXT "$FinishText"
!define MUI_FINISHPAGE_RUN
!define MUI_FINISHPAGE_RUN_TEXT "Open the folder with the installer for the crew PCs"
!define MUI_FINISHPAGE_RUN_FUNCTION OpenClientKit
!define MUI_FINISHPAGE_RUN_NOTCHECKED
!insertmacro MUI_PAGE_FINISH

!insertmacro MUI_UNPAGE_INSTFILES

!insertmacro MUI_LANGUAGE "English"

; ------------------------------------------------------------------ checks
Function .onInit
  ${IfNot} ${RunningX64}
    !insertmacro Refuse 3 "NaviSuite Server needs a 64-bit Windows."
  ${EndIf}
  ${IfNot} ${AtLeastBuild} 14393
    !insertmacro Refuse 3 "NaviSuite Server needs Windows 10 (1607 or later), Windows 11 or Windows Server 2016 or later."
  ${EndIf}
  UserInfo::GetAccountType
  Pop $0
  ${If} $0 != "admin"
    !insertmacro Refuse 3 "Run the NaviSuite Server installer as an administrator."
  ${EndIf}
  System::Call 'kernel32::CreateMutex(p 0, i 0, t "NaviSuiteServerSetup") p .r1 ?e'
  Pop $0
  ${If} $0 = 183
    !insertmacro Refuse 1 "The NaviSuite Server installer is already running."
  ${EndIf}
  SetRegView 64

  ${GetParameters} $Args
  ReadRegStr $0 HKLM "${REG_KEY}" "Root"
  ${If} $0 != ""
    StrCpy $INSTDIR $0
  ${EndIf}
  ClearErrors
  ${GetOptions} $Args "/ROOT=" $0
  ${IfNot} ${Errors}
    StrCpy $INSTDIR $0
  ${EndIf}
  ClearErrors
  ${GetOptions} $Args "/ANSWERS=" $AnswerFile
  ${If} ${Errors}
    StrCpy $AnswerFile ""
  ${ElseIfNot} ${FileExists} "$AnswerFile"
    !insertmacro Refuse 5 "Answer file not found: $AnswerFile"
  ${EndIf}

  ; Installed already? (By this installer, or by the scripts before Checkpoint 8.)
  ReadRegStr $InstalledVersion HKLM "${REG_KEY}" "Version"
  ${If} ${FileExists} "$INSTDIR\app\artisan"
    StrCpy $Mode "upgrade"
  ${Else}
    StrCpy $Mode "install"
  ${EndIf}

  !if ${NS_TEST_BUILD} == 1
    ClearErrors
    ${GetOptions} $Args "/TESTBUILD" $0
    ${If} ${Errors}
      ${If} ${Silent}
        !insertmacro Refuse 3 "This is a test build of NaviSuite Server; it is installed silently only with /TESTBUILD."
      ${EndIf}
      MessageBox MB_ICONEXCLAMATION|MB_YESNO|MB_DEFBUTTON2 "This is a TEST BUILD of NaviSuite Server. It did not pass the test suite gate and must never be installed on a vessel.$\r$\n$\r$\nInstall it on this test PC anyway?" IDYES +2
      Quit
    ${EndIf}
  !endif

  ; A quick version check here; setup-server.ps1 makes the full one
  ; (minimum version to upgrade from, database schema).
  ${If} $Mode == "upgrade"
  ${AndIf} $InstalledVersion != ""
    !insertmacro VersionCore "$InstalledVersion" $R0
    !insertmacro VersionCore "${NS_VERSION}" $R1
    ${VersionCompare} "$R0" "$R1" $R2
    ${If} $R2 == 1
      ClearErrors
      ${GetOptions} $Args "/ALLOWDOWNGRADE" $0
      ${If} ${Errors}
        !insertmacro Refuse 4 "NaviSuite Server $InstalledVersion is installed, which is newer than this installer (${NS_VERSION}). To go back, use update-server.ps1 -Rollback; to install this older version anyway, run the installer with /ALLOWDOWNGRADE."
      ${EndIf}
    ${EndIf}
  ${EndIf}

  ${If} $Mode == "install"
  ${AndIf} ${Silent}
  ${AndIf} $AnswerFile == ""
    !insertmacro Refuse 5 "A silent install of a new server needs /ANSWERS=<file.ini> (see answers.example.ini)."
  ${EndIf}
  StrCpy $Firewall ${BST_CHECKED}
FunctionEnd

Function .onVerifyInstDir
  StrCpy $R0 0
  ${Do}
    StrCpy $R1 "$INSTDIR" 1 $R0
    ${If} $R1 == ""
      ${ExitDo}
    ${EndIf}
    ${If} $R1 == " "
      Abort
    ${EndIf}
    IntOp $R0 $R0 + 1
  ${Loop}
FunctionEnd

Function SkipUnlessNew
  ${If} $Mode != "install"
    Abort
  ${EndIf}
FunctionEnd

Function SettingsPage
  ${If} $Mode != "install"
  ${OrIf} $AnswerFile != ""
    Abort
  ${EndIf}
  !insertmacro MUI_HEADER_TEXT "Vessel and office connection" "The vessel is required. The office connection can be added later by running this installer again with an answer file, or with install-server.ps1."
  nsDialogs::Create 1018
  Pop $0
  ${NSD_CreateLabel} 0 0u 120u 12u "Vessel id (from the office):"
  ${NSD_CreateNumber} 125u 0u 60u 12u "$VesselId"
  Pop $hVesselId
  ${NSD_CreateLabel} 0 18u 120u 12u "Server name on the LAN:"
  ${NSD_CreateText} 125u 18u 120u 12u "$ServerName"
  Pop $hServerName
  ${NSD_CreateLabel} 250u 18u 50u 12u "(empty: PC name)"
  ${NSD_CreateLabel} 0 40u 120u 12u "Office (cloud) address:"
  ${NSD_CreateText} 125u 40u 175u 12u "$CloudUrl"
  Pop $hCloudUrl
  ${NSD_CreateLabel} 0 58u 120u 12u "Sync node id:"
  ${NSD_CreateText} 125u 58u 175u 12u "$NodeId"
  Pop $hNodeId
  ${NSD_CreateLabel} 0 76u 120u 12u "Sync token:"
  ${NSD_CreatePassword} 125u 76u 175u 12u "$SyncToken"
  Pop $hSyncToken
  ${NSD_CreateLabel} 0 92u 300u 20u "The office gives the node id and token (php artisan sync:register-node). The token is stored encrypted on this PC and never written to the logs."
  ${NSD_CreateCheckbox} 0 116u 300u 12u "Configure Windows Firewall so the crew PCs on this LAN can connect"
  Pop $hFirewall
  ${NSD_SetState} $hFirewall $Firewall
  nsDialogs::Show
FunctionEnd

Function SettingsLeave
  ${NSD_GetText} $hVesselId $VesselId
  ${NSD_GetText} $hServerName $ServerName
  ${NSD_GetText} $hCloudUrl $CloudUrl
  ${NSD_GetText} $hNodeId $NodeId
  ${NSD_GetText} $hSyncToken $SyncToken
  ${NSD_GetState} $hFirewall $Firewall
  ${If} $VesselId == ""
  ${OrIf} $VesselId == "0"
    MessageBox MB_ICONEXCLAMATION|MB_OK "Enter the vessel id."
    Abort
  ${EndIf}
  ${If} $CloudUrl != ""
    StrCpy $0 $CloudUrl 8
    ${If} $0 != "https://"
      MessageBox MB_ICONEXCLAMATION|MB_OK "The office address must start with https://"
      Abort
    ${EndIf}
  ${EndIf}
  ${If} $NodeId != ""
  ${AndIf} $SyncToken == ""
    MessageBox MB_ICONEXCLAMATION|MB_OK "Enter the sync token for this node id, or leave both empty."
    Abort
  ${EndIf}
  ${If} $SyncToken != ""
    ${If} $NodeId == ""
      MessageBox MB_ICONEXCLAMATION|MB_OK "Enter the sync node id for this token, or leave both empty."
      Abort
    ${EndIf}
    StrLen $0 $SyncToken
    ${If} $0 < 32
      MessageBox MB_ICONEXCLAMATION|MB_OK "The sync token is too short. Copy it exactly as the office gave it."
      Abort
    ${EndIf}
  ${EndIf}
FunctionEnd

Function OpenClientKit
  ${If} $ClientKit != ""
    ExecShell "open" "$ClientKit"
  ${EndIf}
FunctionEnd

; ------------------------------------------------------------------ install
Section "NaviSuite Server" SecMain
  SetShellVarContext all
  ${GetTime} "" "L" $0 $1 $2 $3 $4 $5 $6
  !insertmacro Log "$2-$1-$0 $4:$5:$6 NaviSuite Server ${NS_VERSION} (${NS_RELEASE}): $Mode in $INSTDIR, installed before: $InstalledVersion"

  ; The payload goes into <Root>\setup, owned by and open to administrators
  ; and SYSTEM only, so nobody else can change the scripts between unpacking
  ; and running them (the user's TEMP would allow that).
  CreateDirectory "$INSTDIR\setup"
  nsExec::ExecToLog 'icacls "$INSTDIR\setup" /setowner *S-1-5-32-544 /T /C /Q'
  Pop $0
  nsExec::ExecToLog 'icacls "$INSTDIR\setup" /inheritance:r /grant:r *S-1-5-32-544:(OI)(CI)F *S-1-5-18:(OI)(CI)F /T /C /Q'
  Pop $0
  ${If} $0 != 0
    !insertmacro Log "  FAILED to restrict $INSTDIR\setup (icacls exit $0)"
    MessageBox MB_ICONSTOP|MB_OK "Could not restrict access to $INSTDIR\setup. Nothing was installed."
    SetErrorLevel 1
    Abort
  ${EndIf}

  StrCpy $PayloadDir "$INSTDIR\setup\payload-${NS_VERSION}"
  RMDir /r "$PayloadDir"
  DetailPrint "Unpacking NaviSuite ${NS_VERSION} to $PayloadDir"
  SetOutPath "$PayloadDir"
  SetDetailsPrint listonly
  File /r "${NS_PAYLOAD}\*.*"
  SetDetailsPrint both
  SetOutPath "$INSTDIR\setup"

  ; Guided install: the answers (no credentials) go to a file next to the
  ; payload; the sync token goes to setup-server.ps1 through this process's
  ; environment only, never to a file or a command line.
  ${If} $Mode == "install"
  ${AndIf} $AnswerFile == ""
    StrCpy $AnswerFile "$INSTDIR\setup\answers-guided.ini"
    Delete "$AnswerFile"
    WriteINIStr "$AnswerFile" "server" "VesselId" "$VesselId"
    WriteINIStr "$AnswerFile" "server" "ServerName" "$ServerName"
    ${If} $Firewall == ${BST_CHECKED}
      WriteINIStr "$AnswerFile" "server" "Firewall" "1"
    ${Else}
      WriteINIStr "$AnswerFile" "server" "Firewall" "0"
    ${EndIf}
    WriteINIStr "$AnswerFile" "sync" "CloudUrl" "$CloudUrl"
    WriteINIStr "$AnswerFile" "sync" "NodeId" "$NodeId"
  ${EndIf}
  ${If} $SyncToken != ""
    System::Call 'kernel32::SetEnvironmentVariable(t "NAVISUITE_SETUP_SYNC_TOKEN", t "$SyncToken") i .r0'
  ${EndIf}

  StrCpy $0 '"$WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "$PayloadDir\release\deploy\windows\setup-server.ps1" -Payload "$PayloadDir" -Root "$INSTDIR" -ResultFile "$INSTDIR\setup\last-result.ini"'
  ${If} $AnswerFile != ""
    StrCpy $0 '$0 -AnswerFile "$AnswerFile"'
  ${EndIf}
  ClearErrors
  ${GetOptions} $Args "/ALLOWDOWNGRADE" $1
  ${IfNot} ${Errors}
    StrCpy $0 '$0 -AllowDowngrade'
  ${EndIf}
  !if ${NS_TEST_BUILD} == 1
    StrCpy $0 '$0 -AcceptTestBuild'
  !endif
  Delete "$INSTDIR\setup\last-result.ini"
  ; 64-bit PowerShell: services, the registry and Program Files as they are.
  ${DisableX64FSRedirection}
  nsExec::ExecToLog $0
  Pop $ExitCode
  ${EnableX64FSRedirection}
  System::Call 'kernel32::SetEnvironmentVariable(t "NAVISUITE_SETUP_SYNC_TOKEN", p 0) i .r0'
  StrCpy $SyncToken ""
  Delete "$INSTDIR\setup\answers-guided.ini"

  ReadINIStr $R0 "$INSTDIR\setup\last-result.ini" "result" "Status"
  ReadINIStr $R1 "$INSTDIR\setup\last-result.ini" "result" "Url"
  ReadINIStr $R2 "$INSTDIR\setup\last-result.ini" "result" "Fingerprint"
  ReadINIStr $R3 "$INSTDIR\setup\last-result.ini" "result" "Log"
  ReadINIStr $ClientKit "$INSTDIR\setup\last-result.ini" "result" "ClientKit"
  ReadINIStr $R5 "$INSTDIR\setup\last-result.ini" "result" "Message"
  ${If} $ExitCode == "error"
    StrCpy $ExitCode 1
  ${EndIf}
  !insertmacro Log "  setup-server.ps1 exit $ExitCode, status $R0. Log: $R3"

  ; The release and runtimes are copied into place; the payload is not
  ; needed any more.
  RMDir /r "$PayloadDir"

  ${If} $ExitCode != 0
  ${AndIf} $ExitCode != 2
    ${If} $R0 == ""
      StrCpy $R0 "failed"
    ${EndIf}
    DetailPrint "Setup $R0 (exit code $ExitCode). $R5"
    ${IfNot} ${Silent}
      MessageBox MB_ICONSTOP|MB_OK "NaviSuite Server setup $R0.$\r$\n$\r$\n$R5$\r$\n$\r$\nThe full log is $R3"
    ${EndIf}
    SetErrorLevel $ExitCode
    Abort
  ${EndIf}

  ; Programs and Features, and what the next installer needs to know.
  WriteUninstaller "$INSTDIR\setup\${UNINSTALLER}"
  WriteRegStr HKLM "${REG_KEY}" "Root" "$INSTDIR"
  WriteRegStr HKLM "${REG_KEY}" "Version" "${NS_VERSION}"
  WriteRegStr HKLM "${REG_KEY}" "Release" "${NS_RELEASE}"
  WriteRegStr HKLM "${UNINST_KEY}" "DisplayName" "NaviSuite Server"
  WriteRegStr HKLM "${UNINST_KEY}" "DisplayVersion" "${NS_VERSION}"
  WriteRegStr HKLM "${UNINST_KEY}" "Publisher" "NaviSuite"
  WriteRegStr HKLM "${UNINST_KEY}" "InstallLocation" "$INSTDIR"
  WriteRegStr HKLM "${UNINST_KEY}" "UninstallString" '"$INSTDIR\setup\${UNINSTALLER}"'
  WriteRegStr HKLM "${UNINST_KEY}" "QuietUninstallString" '"$INSTDIR\setup\${UNINSTALLER}" /S'
  WriteRegStr HKLM "${UNINST_KEY}" "Comments" "Release ${NS_RELEASE}. Uninstalling keeps the database and files unless you choose otherwise."
  WriteRegDWORD HKLM "${UNINST_KEY}" "NoModify" 1
  WriteRegDWORD HKLM "${UNINST_KEY}" "NoRepair" 1

  StrCpy $FinishText "NaviSuite ${NS_VERSION} is installed ($Mode).$\r$\n$\r$\nCrew PCs open: $R1$\r$\nServer fingerprint: $R2$\r$\n$\r$\nInstaller for the crew PCs: $ClientKit$\r$\n$\r$\nLog: $R3"
  ${If} $ExitCode == 2
    StrCpy $FinishText "NaviSuite ${NS_VERSION} is installed ($Mode), but the health check found problems. Read the log before the crew uses it:$\r$\n$R3$\r$\n$\r$\nCrew PCs open: $R1$\r$\nServer fingerprint: $R2"
  ${EndIf}
  !insertmacro Log "  done: $R1, fingerprint $R2"
  SetErrorLevel $ExitCode
SectionEnd

; ------------------------------------------------------------------ uninstall
Function un.onInit
  UserInfo::GetAccountType
  Pop $0
  ${If} $0 != "admin"
    !insertmacro Refuse 3 "Run the NaviSuite Server uninstaller as an administrator."
  ${EndIf}
  SetRegView 64
  ReadRegStr $0 HKLM "${REG_KEY}" "Root"
  ${If} $0 != ""
    StrCpy $INSTDIR $0
  ${Else}
    ; The uninstaller lives in <Root>\setup.
    ${GetParent} "$INSTDIR" $INSTDIR
  ${EndIf}
  StrCpy $RemoveData 0
  ${GetParameters} $Args
  ClearErrors
  ${GetOptions} $Args "/REMOVEDATA=" $0
  ${IfNot} ${Errors}
    ${If} $0 == "YES"
      StrCpy $RemoveData 1
    ${Else}
      !insertmacro Refuse 5 "/REMOVEDATA must be written /REMOVEDATA=YES (it deletes the database and every backup)."
    ${EndIf}
  ${EndIf}
  ${IfNot} ${Silent}
    MessageBox MB_ICONQUESTION|MB_YESNO|MB_DEFBUTTON2 "Remove the NaviSuite server services from this PC?$\r$\n$\r$\nThe database, uploaded files, backups, settings and credentials are kept in $INSTDIR, so installing again brings the server back as it was." IDYES +2
    Quit
    MessageBox MB_ICONEXCLAMATION|MB_YESNO|MB_DEFBUTTON2 "Also DELETE everything in $INSTDIR, including the database and every backup?$\r$\n$\r$\nThis cannot be undone. Copy $INSTDIR\data\backups to another drive first.$\r$\n$\r$\nYes deletes everything. No keeps the data (recommended)." IDNO +2
    StrCpy $RemoveData 1
  ${EndIf}
FunctionEnd

Section "Uninstall"
  SetShellVarContext all
  ${GetTime} "" "L" $0 $1 $2 $3 $4 $5 $6
  !insertmacro Log "$2-$1-$0 $4:$5:$6 uninstall ${NS_VERSION} from $INSTDIR (remove data: $RemoveData)"

  ; Run the installed release's own uninstall script, from a copy in the
  ; administrators-only setup folder (the app folder is deleted with
  ; /REMOVEDATA=YES).
  StrCpy $1 "$INSTDIR\setup\uninstall-scripts"
  RMDir /r "$1"
  CreateDirectory "$1\lib"
  CreateDirectory "$1\templates"
  CopyFiles /SILENT "$INSTDIR\app\deploy\windows\uninstall-server.ps1" "$1"
  CopyFiles /SILENT "$INSTDIR\app\deploy\windows\lib\*.*" "$1\lib"
  CopyFiles /SILENT "$INSTDIR\app\deploy\windows\templates\*.*" "$1\templates"
  ${IfNot} ${FileExists} "$1\uninstall-server.ps1"
    !insertmacro Log "  FAILED: $INSTDIR\app\deploy\windows\uninstall-server.ps1 not found"
    MessageBox MB_ICONSTOP|MB_OK "The NaviSuite uninstall script was not found in $INSTDIR\app. Nothing was removed."
    SetErrorLevel 1
    Abort
  ${EndIf}
  StrCpy $0 '"$WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "$1\uninstall-server.ps1" -Root "$INSTDIR"'
  ${If} $RemoveData == 1
    StrCpy $0 '$0 -RemoveData -Force'
  ${EndIf}
  ${DisableX64FSRedirection}
  nsExec::ExecToLog $0
  Pop $2
  ${EnableX64FSRedirection}
  ${If} $2 != 0
    !insertmacro Log "  FAILED: uninstall-server.ps1 exit $2"
    ${IfNot} ${Silent}
      MessageBox MB_ICONSTOP|MB_OK "Removing NaviSuite failed (exit code $2). See the messages above. Nothing more was changed; you can run the uninstaller again."
    ${EndIf}
    SetErrorLevel 1
    Abort
  ${EndIf}

  DeleteRegKey HKLM "${UNINST_KEY}"
  DeleteRegKey HKLM "${REG_KEY}"
  ${If} $RemoveData == 1
    RMDir /r "$INSTDIR"
  ${Else}
    RMDir /r "$1"
    Delete "$INSTDIR\setup\${UNINSTALLER}"
    !insertmacro Log "  services removed; data kept in $INSTDIR"
  ${EndIf}
SectionEnd
