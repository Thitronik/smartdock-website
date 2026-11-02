# Gleichwertige Cookie-Buttons

Ausgangsstand: staging `ce010c5b0cf318fc44c601f576ff891506c71fe8`.
„Nur notwendige“ war bisher umrandet, „Alle akzeptieren“ kräftig grün hinterlegt.
Beide Optionen erhalten in Hauptanwendung und Position Hold dieselbe neutrale
Darstellung: transparenter Hintergrund, helle Schrift, grauer Rand, kein Schatten.
Größe, Gewichtung und Hoververhalten sind gleich; Texte, Reihenfolge und getrennte
Einwilligungsaktionen bleiben erhalten. Die Korrektur ist eine gestalterische
Gleichstellung, keine vollständige rechtliche oder technische Cookieprüfung.

Browser, lokaler Pages-Build, Desktop: beide Buttons 161 × 40 px, transparente
Hintergründe, rgb(249, 250, 251) Text, rgb(44, 49, 58) Rand, Schriftgewicht 500,
kein Schatten. Eine Regression wertet die tatsächlich gerenderten Buttonprops
beider Anwendungspakete aus und prüft getrennte Aktionen für notwendige/alle.
