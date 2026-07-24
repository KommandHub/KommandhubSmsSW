# 0.9.0-beta.1

Erste öffentliche Beta. Funktional vollständig für transaktionale SMS; die
Zustellung über die Anbieter wurde gegen deren Fehlerantworten geprüft, aber
noch nicht über ein großes Volumen echter Sendungen – genau dafür ist die
Beta-Phase da. Beginne mit einem Test-Absender und einer kleinen Zielgruppe,
bevor du einen Storefront umstellst.

- Sende transaktionale SMS zusätzlich zu den E-Mails von Shopware. Eine Aktion
  „SMS senden" im Flow Builder stellt eine SMS-Vorlage für jedes Ereignis zu –
  Bestellung aufgegeben, Bestellung versandt, Passwort zurücksetzen – ohne die
  E-Mail zu ersetzen.
- Verwalte SMS-Vorlagen je Ereignis in der Administration, mit einem
  Live-Zähler für Zeichen und Segmente, der anzeigt, wann eine Nachricht in ein
  zweites berechnetes Segment übergeht oder auf Unicode wechselt.
- Wähle aus vier SMS-Anbietern mit automatischem Failover: Termii (Westafrika),
  Sendexa (Ghana, Beta), Africa's Talking (Ostafrika) und Twilio (internationaler
  Fallback). Lege einen Standard fest; das Plugin bevorzugt einen Anbieter, der
  das Zielland abdeckt, und weicht auf die übrigen aus.
- Es ist kein Standardanbieter vorausgewählt: Wähle und konfiguriere einen,
  bevor du live gehst, damit ein Shop nie über einen nicht eingerichteten
  Anbieter sendet.
- „Testnachricht senden" je Vorlage stellt an eine von dir gewählte Nummer zu,
  mit Beispiel-Bestelldaten, und meldet genau zurück, warum eine Sendung
  fehlgeschlagen ist.
- Telefonfelder im Storefront erhalten eine Länder-Vorwahlauswahl neben der
  Nummer, sodass das Land die Wahl des Kunden ist statt einer shopweiten
  Einstellung. Funktioniert bei Registrierung, Adressverwaltung, Checkout und
  CMS-Formularen.
- Ein Kunde ohne verwendbare Mobilnummer wird übersprungen und protokolliert;
  der Rest des Flows, einschließlich der Bestellbestätigungs-E-Mail, läuft
  weiter.
- Zustellstatus-Webhooks werden empfangen und protokolliert, signaturgeprüft je
  Verkaufskanal.
