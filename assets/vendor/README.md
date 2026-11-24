# Self-hosted third-party assets

Everything the app used to pull from public CDNs now lives here, so pages load
with no internet access (ship LAN / offline). Versions are the ones the views
referenced before the switch - upgrade deliberately, not by accident.

| Library            | Version | Was loaded from                  |
|--------------------|---------|----------------------------------|
| jQuery             | 3.3.1   | code.jquery.com                  |
| Popper.js          | 1.12.9  | cdnjs                            |
| Bootstrap CSS      | 4.1.1   | cdnjs                            |
| Bootstrap JS       | 4.0.0   | maxcdn.bootstrapcdn.com          |
| DataTables (+BS4)  | 1.10.19 | cdn.datatables.net               |
| DataTables Buttons | 1.5.2   | cdn.datatables.net               |
| pdfmake            | 0.1.36  | cdnjs                            |
| toastr             | 2.1.3   | cdnjs (".../latest/")            |
| Font Awesome Free  | 5.0.6   | use.fontawesome.com              |
| Themify Icons      | 0.1.2   | jsdelivr                         |
| Pe-icon-7-stroke   | 1.2.3   | jsdelivr                         |

`fonts/` holds Google Fonts (Public Sans, IBM Plex Sans/Mono, Nunito, Open Sans,
Roboto Condensed) as woff2, latin + latin-ext subsets only, with one CSS file
per family set.
