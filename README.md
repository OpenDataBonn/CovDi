# CovDi

Covid-19 Digital
Eigenentwicklung für das Gesundheitsamt zur Unterstützung der Fallbearbeitung während der Corona-Pandemie

Erfassung, Auswertung und automatischer Email-Versand für die Anordnung von Quarantänen, Tätigkeitsverboten und Abstrichen. 
Die Daten von Antikörper-Tests können ebenfalls erfasst werden. 


Hinweis zur Nutzung von CovDi:
!Es handelt sich hier nur um das Frontend, das mit PHP betrieben wird!

Folgende Komponenten werden zusätzlich benötigt:
* Intrexx Server (In Bonn auf Basis eines Windows-Servers mit MS SQL-Datenbank)
* Intrexx OData Connector
* FormSolutions Formulare für die Erfassung von Fällen (Wenn eine Erfassung über einen Callcenter mit Online-Formular gewünscht ist)
* Intrexx-Anwendung Covid-19 Meldungen mit Odata-Funktionen und Prozessen
* IMAP Anbindung des Intrexx-Servers, wenn FormSolutions Formulare genutzt werden sollen

Zur Nutzung muss eine config.php im Unterordner "src" erstellt werden. Das Template ist im gleichen Ordner zu finden.