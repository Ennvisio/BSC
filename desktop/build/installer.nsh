; NaviSuite desktop client: extra installer steps (electron-builder NSIS).
;
; Checks before anything is installed (Checkpoint 8):
;   64-bit Windows 10 or 11; no downgrade over a newer client unless
;   /ALLOWDOWNGRADE is given.
;
; Install:
;   1. A Windows Firewall rule so the discovery answers (UDP, from the LAN
;      only) reach the client without a "Windows Security Alert" prompt.
;   2. The administrator settings folder %ProgramData%\NaviSuite Client,
;      writable by administrators only, so ordinary users cannot plant
;      settings.
;   3. The administrator settings (client-config.json), from the first of:
;        - client-config.json next to the installer (copied as it is);
;        - /SERVER=host[,host2] with optional /FINGERPRINT=AB:CD:...,
;          /VESSEL=<id> and /LOCK, written by NaviSuite.exe itself so the
;          same code checks them that reads them;
;        - nothing given: the existing file is kept (upgrades).
;   4. A log of every run: %ProgramData%\NaviSuite Client\logs\install.log
;
; Silent install for IT:
;   NaviSuite-Client-Setup.exe /S
;   NaviSuite-Client-Setup.exe /S /SERVER=nav-server,192.168.1.10 /FINGERPRINT=AB:CD:... /VESSEL=12 /LOCK
; Exit code 0 on success; 3 when this PC is not supported; 4 when a newer
; client is installed; 5 when the settings given were refused (the client
; is installed, the old settings are kept).
;
; Uninstall keeps the administrator settings, so a reinstall finds the
; server again. /REMOVESETTINGS deletes them too. User settings (the chosen
; server, per Windows user) are kept either way, as before.

!include LogicLib.nsh
!include x64.nsh
!include WinVer.nsh
!include FileFunc.nsh
!include WordFunc.nsh

!define NS_CLIENT_DATA "NaviSuite Client"
; Used by the installer only; the uninstaller is compiled separately and
; would warn about an unused variable (warnings fail the build).
!ifndef BUILD_UNINSTALLER
  Var nsPreviousVersion
!endif

; Appends one line to the install log. The folder may not exist yet on the
; first run (before customInstall), so refusals go to %TEMP%.
!macro NsClientLog TEXT
  Push $9
  Push $8
  SetShellVarContext all
  StrCpy $8 "$APPDATA\${NS_CLIENT_DATA}\logs"
  IfFileExists "$APPDATA\${NS_CLIENT_DATA}\*.*" +2 0
    StrCpy $8 "$TEMP"
  CreateDirectory "$8"
  ClearErrors
  FileOpen $9 "$8\install.log" a
  ${IfNot} ${Errors}
    FileSeek $9 0 END
    FileWrite $9 "${TEXT}$\r$\n"
    FileClose $9
  ${EndIf}
  Pop $8
  Pop $9
!macroend

!macro NsClientRefuse CODE TEXT
  !insertmacro NsClientLog "REFUSED (exit ${CODE}): ${TEXT}"
  ${IfNot} ${Silent}
    MessageBox MB_ICONSTOP|MB_OK "${TEXT}"
  ${EndIf}
  SetErrorLevel ${CODE}
  Quit
!macroend

; "1.2.3-test.abc" -> "1.2.3", for VersionCompare.
!macro NsVersionCore IN OUT
  Push $R8
  Push $R9
  StrCpy $R8 0
  StrCpy ${OUT} "${IN}"
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

!macro customInit
  ${IfNot} ${RunningX64}
    !insertmacro NsClientRefuse 3 "NaviSuite needs a 64-bit edition of Windows 10 or 11."
  ${EndIf}
  ${IfNot} ${AtLeastBuild} 10240
    !insertmacro NsClientRefuse 3 "NaviSuite needs Windows 10 or 11 (64-bit)."
  ${EndIf}

  ReadRegStr $nsPreviousVersion HKLM "${UNINSTALL_REGISTRY_KEY}" "DisplayVersion"
  !ifdef UNINSTALL_REGISTRY_KEY_2
    ${If} $nsPreviousVersion == ""
      ReadRegStr $nsPreviousVersion HKLM "${UNINSTALL_REGISTRY_KEY_2}" "DisplayVersion"
    ${EndIf}
  !endif
  ${If} $nsPreviousVersion != ""
    !insertmacro NsVersionCore "$nsPreviousVersion" $R0
    !insertmacro NsVersionCore "${VERSION}" $R1
    ${VersionCompare} "$R0" "$R1" $R2
    ${If} $R2 == 1
      ${GetParameters} $R3
      ClearErrors
      ${GetOptions} $R3 "/ALLOWDOWNGRADE" $R4
      ${If} ${Errors}
        !insertmacro NsClientRefuse 4 "NaviSuite client $nsPreviousVersion is installed, which is newer than this installer (${VERSION}). Uninstall it first, or run this installer with /ALLOWDOWNGRADE."
      ${EndIf}
    ${EndIf}
  ${EndIf}
!macroend

!macro customInstall
  nsExec::Exec 'netsh advfirewall firewall delete rule name="NaviSuite client discovery"'
  nsExec::Exec 'netsh advfirewall firewall add rule name="NaviSuite client discovery" dir=in action=allow program="$INSTDIR\NaviSuite.exe" protocol=UDP remoteip=localsubnet profile=domain,private description="Answers to NaviSuite server discovery on the LAN"'

  SetShellVarContext all
  CreateDirectory "$APPDATA\${NS_CLIENT_DATA}"
  nsExec::Exec 'icacls "$APPDATA\${NS_CLIENT_DATA}" /inheritance:r /grant:r *S-1-5-32-544:(OI)(CI)F *S-1-5-18:(OI)(CI)F *S-1-5-32-545:(OI)(CI)RX'
  CreateDirectory "$APPDATA\${NS_CLIENT_DATA}\logs"

  ${GetTime} "" "L" $R0 $R1 $R2 $R3 $R4 $R5 $R6
  StrCpy $R7 "guided"
  ${If} ${Silent}
    StrCpy $R7 "silent"
  ${EndIf}
  ${If} $nsPreviousVersion == ""
    !insertmacro NsClientLog "$R2-$R1-$R0 $R4:$R5:$R6 install ${VERSION} ($R7) in $INSTDIR"
  ${Else}
    !insertmacro NsClientLog "$R2-$R1-$R0 $R4:$R5:$R6 upgrade $nsPreviousVersion -> ${VERSION} ($R7) in $INSTDIR"
  ${EndIf}

  ${GetParameters} $R0
  IfFileExists "$EXEDIR\client-config.json" 0 nsCfgFromOptions
    CopyFiles /SILENT "$EXEDIR\client-config.json" "$APPDATA\${NS_CLIENT_DATA}\client-config.json"
    !insertmacro NsClientLog "  administrator settings copied from $EXEDIR\client-config.json"
    Goto nsCfgDone
  nsCfgFromOptions:
  ClearErrors
  ${GetOptions} $R0 "/SERVER=" $R1
  IfErrors nsCfgKept
    StrCpy $R5 '"$INSTDIR\${APP_EXECUTABLE_FILENAME}" --navisuite-write-managed-config "--server=$R1"'
    ClearErrors
    ${GetOptions} $R0 "/FINGERPRINT=" $R2
    ${IfNot} ${Errors}
      StrCpy $R5 '$R5 "--fingerprint=$R2"'
    ${EndIf}
    ClearErrors
    ${GetOptions} $R0 "/VESSEL=" $R3
    ${IfNot} ${Errors}
      StrCpy $R5 '$R5 "--vessel=$R3"'
    ${EndIf}
    ClearErrors
    ${GetOptions} $R0 "/LOCK" $R4
    ${IfNot} ${Errors}
      StrCpy $R5 '$R5 --lock'
    ${EndIf}
    ExecWait $R5 $R6
    ${If} $R6 == 0
      !insertmacro NsClientLog "  administrator settings written: server $R1, fingerprint $R2, vessel $R3"
    ${Else}
      !insertmacro NsClientLog "  FAILED (exit $R6): the settings /SERVER=$R1 /FINGERPRINT=$R2 /VESSEL=$R3 were refused; the previous settings are kept"
      ${IfNot} ${Silent}
        MessageBox MB_ICONEXCLAMATION|MB_OK "The server settings given to the installer were refused (check /SERVER=, /FINGERPRINT= and /VESSEL=). NaviSuite is installed; the previous settings are kept."
      ${EndIf}
      SetErrorLevel 5
    ${EndIf}
    Goto nsCfgDone
  nsCfgKept:
  IfFileExists "$APPDATA\${NS_CLIENT_DATA}\client-config.json" 0 nsCfgNone
    !insertmacro NsClientLog "  administrator settings kept"
    Goto nsCfgDone
  nsCfgNone:
  !insertmacro NsClientLog "  no administrator settings: the client finds the server on the LAN"
  nsCfgDone:
  !insertmacro NsClientLog "  done"
!macroend

!macro customUnInstall
  nsExec::Exec 'netsh advfirewall firewall delete rule name="NaviSuite client discovery"'
  SetShellVarContext all
  ${ifNot} ${isUpdated}
    ${GetParameters} $R0
    ClearErrors
    ${GetOptions} $R0 "/REMOVESETTINGS" $R1
    ${IfNot} ${Errors}
      RMDir /r "$APPDATA\${NS_CLIENT_DATA}"
      !insertmacro NsClientLog "uninstalled ${VERSION}; administrator settings and logs removed (/REMOVESETTINGS)"
    ${Else}
      !insertmacro NsClientLog "uninstalled ${VERSION}; administrator settings kept in $APPDATA\${NS_CLIENT_DATA}"
    ${EndIf}
  ${EndIf}
!macroend
