# „Töö Värk“ arhitektuur

Alljärgnevalt on süsteemi arhitektuur lahti kirjutatud üldisemast pildist kuni spetsiifiliste algoritmide ja detailideni.

## 1. Üldine Arhitektuur ja Filosoofia (High-Level)

**1.1. Null-sõltuvuse (Zero-dependency) ja Single-File printsiip**
Süsteem ei kasuta ühtegi välist raamistikku. Kogu rakendus koosneb puhtast (Vanilla) PHP-st (v8.3+), JavaScriptist ja CSS-ist.
Produktsiooni viimiseks võib serverisse tõsta ('index.php', './src' ja './plugins') või   kasutada kompilaatorit (`compile.php`), mis kleebib kogu rakenduse ja pistikprogrammid (plugins) kokku **üheks failiks** (`index_release.php`). Serverisse on vaja tõsta vaid see fail ja luuakse lokaalne SQLite andmebaas. Igaljuhul ära unusta määrata andmebaasile turvaline asukoht!

**1.2. Andmebaasikeskne äriloogika**
Rakenduse kõige fundamentaalsem otsus on see, et **äriloogikat ei dubleerita PHP-s, kui andmebaasi piirangud saavad selle ära lahendada**. Kogu ülesannete loogika baseerub SQLite'i `UNIQUE`, `ON CONFLICT DO UPDATE` ja `ON DELETE CASCADE` funktsionaalsustel. Andmebaas töötab WAL (*Write-Ahead Logging*) režiimis, mis tagab, et kümned kasutajad saavad samaaegselt lugeda ja kirjutada ilma "database locked" vigadeta.

**1.3. API-First ja SPA-laadne (Single Page App) käitumine**
Kuigi tegu on PHP rakendusega, genereerib PHP serveris vaid tühja HTML-kesta (shell). Kogu andmevahetus käib läbi REST API, kasutades URL-i parameetrit `?api=...`. Lehte ei laeta kunagi täielikult uuesti, JS toob JSON andmed ja joonistab need ekraanile.

---

## 2. Süsteemi kihid ja koodibaasi struktuur

Süsteem on jagatud rangeteks kihtideks (MVC-laadne, aga lihtsustatud):

*   **Entry point & Context (`index.php`):** Käivitab sessiooni, loob CSRF tokeni, ühendab andmebaasi ja määratleb globaalsed autoriseerimise muutujad (`$uid`, `$is_admin`, `$logged_in`).
*   **Ruuter (`src/api.php`):** Väga õhuke kiht. Püüab kinni kõik `?api=` päringud, kontrollib CSRF tokenit ja õigusi (nt kas tegu on adminiga) ning suunab päringu edasi handlerisse. Kasutab ETag vahemällu salvestamist.
*   **Kontrollerid (`src/api_handlers.php`):** Sisaldab puhtaid funktsioone (P11 muster). Iga funktsioon võtab vastu andmebaasi objekti ja sisendandmed ning tagastab massiivi `[HTTP_KOOD, JSON_KEHA]`. Globaalsete muutujate kasutamine siin on keelatud, mis teeb need lihtsalt testitavaks.
*   **Abifunktsioonid (`src/helpers.php`):** Abistavad puhtad funktsioonid (IP tuvastus, andmebaasi abstraheerimine, valideerimine).
*   **View Kest (`src/views.php`):** HTML šabloonid ja tühjad konteinerid (`<div id="team-tasks-container"></div>`). Andmeid siin ei pärita ega sisestata.
*   **Kliendipool (Frontend - `app.js`, `init.js`, `style.css`):** Tõmbab API-st andmed ja manipuleerib DOM-i.
*   **Pistikprogrammid (`plugins/`):** Modulaarne süsteem lisafunktsioonidele (nt `audit2.php` kvaliteedikontrolli jaoks, `config.php` seadetele). Need lisavad dünaamiliselt uusi API teekondi ja vaateid.

---

## 3. Põhialgoritmid ja kriitilised lahendused

### 3.1. Smart Merge & True Sync (Ülesannete genereerimise tuum)
Rakenduse suurimaks tehniliseks väljakutseks on automaatsete töögraafikute genereerimine kuuks ajaks, ilma et administraator kirjutaks üle tööliste poolt juba alustatud või muudetud ülesandeid. See on andmebaasi operatsioon:

1.  **True Sync:** Kui administraator vajutab "Genereeri", kustutab algoritm kõigepealt ära kõik tuleviku ülesanded, mis on staatuses `0` (ootel) ja loodud automaatselt (`source = 'rule'`).  Manuaalselt lisatud (`source = 'manual'`) või juba alustatud (`status > 0`) ülesandeid *ei puudutata*.
2.  **Smart Merge:** Andmebaasi on defineeritud range piirang: `UNIQUE(user_id, task_date, title)`. Generaator püüab sisestada uusi ülesandeid päringuga `INSERT OR IGNORE`. Kui töötajal on juba sellel päeval samanimeline ülesanne (isegi kui ta on selle ise loonud või aegu muutnud), siis SQLite lihtsalt ignoreerib uut sisestust. Kokkupõrkeid (conflicts) ja andmete ülekirjutamist ei eksisteeri.

### 3.2. ISO Nädalate mootor (ISO Week Engine)
Tööreeglite ("iga kuu teine nädal", "kord kuus") arvutamiseks ei kasutata lihtsat jagamist. Programm arvutab iga sihtkuu kohta välja **ISO täisnädalad** (nädalad, kus esmaspäevast pühapäevani on kõik 7 päeva antud kuu sees). 
*Algoritm:* Arvutatakse välja iga päeva `date('W')` (ISO nädal). Kui mingil ISO nädalal on kuus vähem kui 7 päeva, ignoreerib automaatgeneraator seda nädalat. Ülejäänud täisnädalad saavad relatiivse indeksi (1 kuni 4) ja graafik genereeritakse täpselt nende põhjalt.

### 3.3. Surgical DOM Updates (Kirurgilised DOM-i uuendused)
Kui töötaja muudab ülesande staatust (Ootel -> Töös -> Tehtud): 
*   JS saadab API-le muudatuse JSON-is.
*   Edulise vastuse (HTTP 200) korral uuendatakse mälus olevat andmemudelit (`Object.assign()` abil).
*   DOM-is muudetakse *ainult* spetsiifilise HTML elemendi teksti ja eemaldatakse/lisatakse vajalik CSS klass (`btn-red` -> `btn-orange`). 

### 3.4. Kompileeritud stringimallid (Compiled String Templates)
*Algoritm:* `compileTpl()` võtab stringi (nt `<b>{{name}}</b> is {{age}}`) ja parsitakse lehe laadimisel *ühe korra* algosadeks. Funktsioon tagastab anonüümse JS funktsiooni, mis teeb stringide otsest liitmist (`return "<b>" + data.name + "</b> is " + data.age;`). 

### 3.5. Laisk laadimine (Lazy Loading via IntersectionObserver)
Suurte andmemahtude joonistamisel ei loo JS korraga kogu kuu sisu. DOM-i sisestatakse vaid "tühjad kestad" (`<div data-date="..." data-lazy="1"></div>`). Kui kasutaja kerib ja kest jõuab ekraani (viewporti) lähedale (~200px), käivitab `IntersectionObserver` kesta sisu renderdamise. See tagab, et isegi 40 töötajaga andmebaasi puhul ei jää mobiilibrauser lehe laadimisel ootama.

---

### 4. Turvalisus ja Autentimine

*   **Tsentraliseeritud autoriseerimiskontekst:** Et vältida vigu, kus üks fail loeb `$_SESSION` ja teine ei loe, laetakse autentimise andmed lahti vaid ühes kohas (`index.php`). Teised failid (nt API ja views) pärivad valmis muutujad (nt `$uid`, `$is_admin`).
*   **CSRF Kaitse:** Iga sessiooni alguses genereeritakse turvaline token (`random_bytes(16)`). See lisatakse HTML `<head>`-i. Iga kliendi `POST` päring paneb selle HTTP päisesse `X-CSRF-Token`. Ruuter keeldub töötlemast ühtegi kirjutavat päringut, kui token ei klapi.
*   **Rate Limiting (Päringute piiramine):** Brute-force rünnakute takistamiseks haldab andmebaasi `rl` (Rate Limit) tabel ebaõnnestunud sisselogimisi IP järgi. Toetatud on ka Cloudflare ja proxy-de taga olemise tuvastus.
*   **Päringute parametriseerimine:** 100% andmebaasipäringutest, mis sisaldavad muutujaid, kasutavad PDO Prepared Statemente (`?` märgiseid), välistades SQL-süstimise täielikult.

---

### 5. Kompilaator (`compile.php`)

Kuna nõue on "ühe-faili-juurutus" (Single-file deploy), on süsteemi sisse ehitatud kompilaator:
1.  **Failide kogumine:** Skript loeb `index.php`-s olevaid `include_once` käske.
2.  **Koodi tihendamine (Minification):** PHP failidest eemaldatakse kommentaarid ja liigsed tühikud ohutult kasutades PHP sissehitatud `php_strip_whitespace()` ja tokenisaatorit. Pikkade ridade vältimiseks sisestab kompilaator reavahetuse ainult funktsioonide ja klasside lõppu.
3.  **Dünaamiline pistikprogrammide kleepimine:** Kõik `plugins/*.php`, `plugins/*.js` ja `plugins/*.css`  liidetakse kokku failiks (`index_release.php`).

---

### 6 Backend: `src/api_handlers.php` (API Kontrollerid)

See fail sisaldab kõiki südamiku (core) API lõpp-punktide (endpointide) funktsioone. Süsteem kasutab ranget **P11 mustrit**: ükski siin olev funktsioon ei tooda HTML-i ega kasuta globaalseid muutujaid (v.a üks erand sätete jaoks). Iga funktsioon võtab vastu kindlad parameetrid (PDO andmebaasiühendus, sisendandmed, kasutaja ID jne) ning tagastab massiivi kujul HTTP koodi ja JSON-keha (nt `[200, ['msg' => 'ok']]`).

#### 6.1 Ülesannete pärimine (Read)
Need funktsioonid varustavad kliendiprogrammi andmetega.
*   **`api_tasks_today()`**: Kliendi "Täna" vaade. Otsib andmebaasist välja töötaja tänased ja *eilsed lõpetamata* ülesanded. Kasutab abifunktsiooni `timeShift()`, mis nihutab ülesande algusaja reaalsele hetkele, kui töötaja vajutab nupule "Alusta", säilitades planeeritud kestvuse.
*   **`api_tasks_month()`**: "Kuu ja Reeglid" vaade. Tagastab ühe töötaja terve kuu ülesanded, tema JSON-kujul reeglid, kolleegide nimekirja ja ISO täisnädalate info. Optimeeritud ühe SQL päringuga korjama ka eelmise kuu andmeid.
*   **`api_tasks_team()`**: Administraatori "Meeskonna" vaade. Tagastab kõikide töötajate ülesanded (kas tänase päeva lõikes kellaaegade kaupa või kuu lõikes kuupäevade kaupa).
*   **`api_tasks_print()`**: Prindivaate andmed. Teeb SQL `JOIN` päringu ülesannete ja `task_details` (objektide andmete) vahel, lisades aadressid ja kontaktisikud, et joonistada prinditavad töölehed.

#### 6.2 Ülesannete haldus (Write)
Kõik kirjutavad funktsioonid kasutavad `db_try()` ümbrist, mis püüab kinni andmebaasivead (sh `UNIQUE` piirangu rikkumised) ja tagastab kliendile turvalise JSON veateate (mitte kunagi toores SQL-viga).
*   **`api_tasks_save()`**: Loob uue või uuendab olemasolevat ülesannet. Kasutab `INSERT ... ON CONFLICT DO UPDATE` loogikat. Uue ülesande lisamisel küsib kohe uue `id` andmebaasist ja tagastab selle kliendile.
*   **`api_tasks_status()`**: Haldab staatuste loogikat (0 -> 1 -> 2). Kliendilt tuleb palve staatus tõsta ning funktsioon kontrollib, kas tegu on õige töötajaga ja et staatus ei läheks üle 2 (Tehtud).
*   **`api_tasks_delete()`**: Kustutab ülesande. Sisaldab ranget kaitset (`status < 2`), et juba tehtuks märgitud ülesandeid ei saaks kustutada.
*   **`api_tasks_batch()`**: Admini tööriist, mis lubab ühe nupuvajutusega määrata sama ülesande mitmele töötajale korraga.

#### 6.3 Graafiku automaatika
*   **`api_rules_generate()`**: 
    1. Parsib JSON reeglid.
    2. Teostab **True Sync** puhastuse (kustutab sihtkuust kõik automaatselt loodud, aga veel alustamata ülesanded).
    3. Käib läbi kuu kõik päevad, arvutab ISO nädalad ja võrdleb neid reeglites defineeritud päevade (E, T, K) ja nädalatega (1, 2, 3, 4).
    4. Sisestab tuhandeid kirjeid kasutades **Smart Merge**'i (`INSERT OR IGNORE`), kaitstes käsitsi tehtud muudatusi ülekirjutamise eest.

##### Samm-sammuline algoritm:
1. **JSON parsib:** Funktsioon võtab vastu kliendi saadetud JSON stringi (`$d['rules_txt']`) ja teeb sellest PHP massiivi.
2. **True Sync (Puhastus):** Alustatakse andmebaasi transaktsiooni (`$pdo->beginTransaction()`). Esimene SQL päring on kustutab ainult sihtkuu (`$ym_first` kuni `$next_first`) ülesanded, mis on **ootel (`status=0`)** ja **automaatselt loodud (`source='rule'`)**:
`DELETE FROM tasks WHERE user_id=? AND task_date >= ? AND task_date < ? AND status=0 AND source='rule'`
Käsitsi lisatud (`source='manual'`) või juba alustatud/tehtud ülesanded jäävad puutumata.
3. **Päevade tsükkel ja ISO nädalad:** Tehakse tsükkel kuu esimesest päevast viimaseni (`for ($day=1; $day <= $days_in_month; $day++)`).
* Väliselt abifunktsioonilt `full_weeks_info()` saadakse *mapping*, mis muudab kalendri ISO nädalad (nt nädal 11, 12, 13) relatiivseteks täisnädalateks antud kuu sees (1, 2, 3, 4).
* Kui päev asub poolikus nädalas (kuu alguses/lõpus), siis relatiivne nädal on `0` ja generaator hüppab järgmisele päevale (`continue`).
4. **Reeglite sobitamine:** Iga reegli puhul kontrollitakse, kas tänane nädalapäev (1-7) ja relatiivne nädal (1-4) on reegli stringis olemas:
`$match = (strpos($day_col, (string)$day_num) !== false) && (strpos((string)$r['weeks'], (string)$rel_week) !== false);`
5. **Smart Merge (Sisestus):** Kui on *match*, käivitatakse `INSERT OR IGNORE INTO tasks`.
* Kui töötajal on sellel kuupäeval sama pealkirjaga ülesanne juba olemas, siis andmebaas ignoreerib seda päringut ja viga ei visata. See on kogu süsteemi stabiilsuse alus.
1. Lõpus tehakse `$pdo->commit()`.

#### 6.4 `api_tasks_today()` – Töötaja ajaline nihe (`timeShift`)
See funktsioon ei tee lihtsalt `SELECT * FROM tasks WHERE date = today`. 

* **Eilsed lõpetamata tööd:** Päring toob nii tänased kui ka eilsed ülesanded. Koodis on aga filter:
`if ($r['status'] == 2 && $r['task_date'] != $today_date) continue;`
See tähendab: kui eilne töö on valmis (`status 2`), peida see ära. Kui aga jäi pooleli (`status 0` või `1`), näita seda ka täna, et töötaja saaks selle lõpetada (nt üle südaöö kestvad vahetused).
* **Ajaline nihe (`timeShift`):** Kujutame ette, et reegel ütles: töö on 08:00–16:00 (8h). Töötaja jõuab objektile ja vajutab "Alusta" alles kell 09:00. `api_tasks_today` käivitab tsüklis abifunktsiooni `timeShift($r['start_time'], $r['end_time'])`. See funktsioon näeb, et reaalne kell on 09:00 ja nihutab lõpuaja automaatselt 17:00 peale, saates kliendile alla juba uued kellaajad. See tagab, et töötaja ei kaota planeeritud tundide mahtu(ei pea kirjet käsitsi parandama), kui ta alustab hiljem.

#### 6.5 `api_tasks_save()` – "Surgical DOM" tagala turvamine
Selleks, et frontend saaks teha kirurgilisi uuendusi (ilma lehte laadimata), peab ta teadma ülesande ID-d. Uue ülesande lisamisel frontend seda aga ei tea.

* Funktsioon kasutab `db_try()` ümbrist, mis püüab automaatselt kinni andmebaasi vead (kui admin üritab samale päevale luua topelt nimega ülesannet, tagastatakse viisakalt HTTP 400 `Worker already has a task with this title on this date.`).
* Kui tegu on INSERT (mitte UPDATE) operatsiooniga, kutsub kood `upsert_task()` abifunktsiooni, mis teeb `ON CONFLICT DO UPDATE`. Kuna SQLite'i `lastInsertId()` võib tagastada 0, kui konflikt viis UPDATE-ni, on funktsioonis kohe järel järelevalve-päring:
`SELECT id FROM tasks WHERE user_id=? AND task_date=? AND title=?`
See tagab, et frontend saab ALATI tagasi kehtiva `id`, et joonistada uus kaart või uuendada olemasolevat.

#### 6.6 Süsteemi ja andmete haldus
*   **`api_users_*` perekond** (`list`, `create`, `update`, `delete`, `password`): Kasutajate CRUD (loo, loe, uuenda, kustuta). Hoolitseb paroolide bcryptiga räsimise (hashimise) ja paroolivahetuse valideerimise eest.
*   **`api_details_*` perekond**: Objektide asukohtade (aadress, kontaktisik) haldus. `api_details_get()` on tugevalt optimeeritud – see toob admini lehe esmasel laadimisel ühe API päringuga korraga nii objektid, kasutajad, süsteemi sisu kui ka andmebaasi tervisliku seisundi (WAL staatuse), et säästa HTTP päringuid.

---

### 7. Frontend: `src/app.js` (Kliendipoolne Mootor)

See fail on klassikaline SPA (Single Page Application) mootor, aga kirjutatud 100% Vanilla JS-is. Ta vastutab API-st andmete küsimise ja nende reaalajas ekraanile manööverdamise eest.

#### 7.1 Süsteemi Initsialiseerimine ja API ühendus
Kõik algab `DOMContentLoaded` sündmusest, mis vaatab globaalset muutujat `CURRENT_VIEW` ja käivitab vastava alglaaduri (nt `initTodayView()`, `initRulesView()`).
* **`apiCall(url, data)` & `apiGet(url)`**: Abifunktsioonid, mis räägivad serveriga (fetch). `apiCall` lisab automaatselt igale POST-päringule PHP poolt HTML-i lisatud konstandi `CSRF_TOKEN` ja teeb  `headers: { 'X-CSRF-Token': CSRF_TOKEN, ... }` päise turvalisuse tagamiseks. Lisaks mõõdavad need funktsioonid aega, et raporteerida võrgu- ja renderduskulusid.
* **Jõudluse Mõõtmine:** Mõõdab, kaua oodati serverit (Network time) ja eraldab selle hilisemast DOM-i renderdamise ajast. Süsteemi allosas on koodiplokk, mis saadab need ajad taustal `?api=debug_log/jstimer` peale, et administraator saaks jälgida, kus asub süsteemi pudelikael (kas andmebaas aeglane või renderdamine brauseris).

#### 7.2 Renderdamine ja Laisk Laadimine (Lazy Loading)
Töö Värk kasutab kompileeritud stringimalle (määratletud `init.js` failis funktsiooniga `compileTpl()`).

*   **`renderTeamTasks()`, `renderTasks()`, `renderUserRows()`**: Võtavad JSON andmed ja toodavad HTML-i. Suurte andmemahtude (nt 1000+ kuu ülesannet) puhul kasutatakse **Laiska laadimist**.
 *   Nendes funktsioonides ei joonistata kogu sisu korraga välja. DOM-i visatakse tühjad kestad (`<div data-lazy="1"></div>`).
 *   Brauseri `IntersectionObserver` jälgib kerimist. Kui kest jõuab ekraanile, kutsutakse välja spetsiifiline täitja-funktsioon (nt `_buildRows()`), mis asendab kesta reaalse sisuga. See hoiab mobiilibrauseri renderdusaja all (alla 10ms).

#### 7.3 Kirurgilised DOM-i Uuendused (Surgical Updates)
Selle asemel, et peale andmete muutmist kogu leht uuesti API-st laadida, manipuleerib JS lokaalset andmemudelit ja ekraani "kirurgiliselt".

#### 7.3.1 `saveTaskUI(e)` – Kirurgiline DOM-i uuendus (P12)
See on kogu rakenduse kõige olulisem UI-funktsioon, mis reageerib ülesande vormi salvestamisele.

1. **Andmete korjamine:** Saadab andmed API-sse(`api_tasks_save`). `e.preventDefault()` peatab lehe laadimise. Loetakse vormi andmed: `const fd = new FormData(form); const d = Object.fromEntries(fd.entries());`
2. **API päring:** Tehakse `await apiCall('tasks/save', d)`. HTTP 200 saamise järel uuendab lokaalset JS muutujat (`teamData` või `rulesData`) uute andmetega in-place (nt `Object.assign()`).
3. **Mälu sünkroniseerimine (State update):** Kui API tagastab HTTP 200, otsitakse mälus olevast JS-objektist (`rulesData.grouped` või `teamData.grouped`) üles see konkreetne ülesanne ja tehakse in-place uuendus:
`Object.assign(taskObj, d);`
Nüüd on JS-i mälus uued kellaajad, märkmed ja staatused.
4. **DOM-i kirurgia:** Ei laeta uut HTML-i! Kood otsib DOM-ist otse selle rea:
`const row = document.querySelector('.team-row[data-id="' + d.id + '"]');`
Kui rida leitakse, uuendatakse reaalajas (ilma renderdamiseta) vaid vajalikke tekstisõlmi:
`row.querySelector('.t-time').textContent = ...`
`row.querySelector('.t-notes').textContent = d.notes;`
Staatuse tekst asendatakse ja `setStatusBtn()` abil eemaldatakse vanad CSS klassid (nt `.btn-orange`) ja lisatakse uued.
1. **Välgutamine:** Lõpuks kutsutakse välja `flashRow(row)`. See kerib ekraani vaikselt muudetud reani ja lisab CSS klassi `highlight-flash`, mis paneb rea korraks kollaselt helendama, andes kasutajale tagasisidet "Salvestatud!".
2. **`setStatusBtn()`, `syncStatusColor()`**: Vahetavad lennult nuppude värve (Punane -> Oranž -> Roheline) lisades ja eemaldades CSS klasse `.btn-red`, `.btn-orange` jne, ilma et peaks HTML struktuuri puutuma.


#### 7.3.2 Visuaalne Reeglite Redaktor (Visual Editor)
Andmebaas ja API suhtlevad reeglite osas ainult puhta JSON-koodiga (mida hoitakse `<textarea>`-s). Kuna tavakasutaja ei oska JSON-it kirjutada, lahendab `app.js` selle sünkroniseerimise teel.
*   **`syncTextToVisual()`**: Parsib JSON-i lahti ja joonistab ekraanile visuaalsed read nädalapäevade (E, T, K) ja nädalate (W1, W2) linnukestega ning aja-sisenditega.
*   **`syncVisualToText()`**: Kuulab kõiki linnukeste ja kellaaegade muutusi visuaalses liideses, transleerib need tagasi kompaktseks koodiks (nt "135", "1234") ja kirjutab varjatult `<textarea>` JSON-isse.
*   See lahendus eraldab täielikult backendi keerukusest – server ei tea visuaalsest liidesest midagi, tegeledes ainult JSON massiiviga. 

#### 7.3.3 Admini Paneeli Logika
*   **`saveUser()`, `deleteUser()`, `saveDetails()`**: Kasutajate ja asukohtade halduse UI loogika. Analoogselt ülesannetele muudavad nad DOM-i kirurgiliselt (lisavad `div`-i või eemaldavad selle `.remove()` abil), säilitades mälus oleva `usersCache` massiivi terviklikkuse.

Lühidalt: `api_handlers.php` on range, olekutu (stateless) andmekaitsja, samal ajal kui `app.js` on äärmiselt optimeeritud ja mälusäästlik manipulaator, mis pakub kasutajale kiiret ja sujuvat (lehevärskendusteta) kogemust.

#### 7.3.4 `renderTeamTasks()` ja `scrollToToday()` – Laisk Laadimine (Lazy Loading)
Suurte andmemahtude (nt 1000+ ülesannet kuus) korraga joonistamine jooksutaks mobiilibrauseri kokku.

* **DOM-i ettevalmistus:** `renderTeamTasks` jookseb läbi kuupäevade ja paneb DOM-i ainult nn "kestad":
`<div data-date="2026-04-18" data-lazy="1"></div>`
Selles faasis HTML-i sisu ei genereerita ja renderdusaeg on tühine (~2-3ms).
* **IntersectionObserver (`lazyRender` abifunktsioon):** Brauserile antakse käsk: "Kui see tühi kest jõuab kerides ekraanile lähemale kui 200px, siis palun käivita sisu joonistamine (`_buildRows()`)". Kest täidetakse hetk enne seda, kui kasutaja seda näeb. See jagab renderduskoormuse väikesteks tükkideks kerimise ajal.
* **Tänasele kerimine (`scrollToToday`):** Kui kasutaja vajutab nupule "Täna", tekib probleem: "Tänane" kast on allpool, pole ekraanil ja seega on tühi (`data-lazy="1"`). `scrollToToday` funktsioon on tark: ta otsib DOM-ist üles `data-date="2026-04-18"` kesta. Enne `scrollIntoView()` kutsumist vaatab ta: kas see on `lazy`? Kui jah, siis ta paneb sinna jõuga kohe sisu sisse (`_buildRows()`), eemaldab `data-lazy` lipu ja alles siis kerib. Kasutaja ei näe kunagi vilksatavat tühja ekraani.

#### 7.3.5 `syncTextToVisual()` ja `syncVisualToText()` – Illusiooni loomine
Need kaks funktsiooni on "sild" andmebaasi toore JSON-teksti ja kasutajasõbraliku kastikeste-liidese vahel.

* **`syncTextToVisual` (Andmetest UI-sse):** Loeb `<textarea id="rules-textarea">` seest toore JSON-i: `[{"days":"135", "weeks":"1234"...}]`. Parsib selle lahti, kloonib spetsiaalset `<template id="visual-rule-template">` rida iga reegli kohta. Siis käib tsükliga üle linnukeste (checkboxid). Kui `checkbox.value` (nt "3" ehk Kolmapäev) leidub stringis "135", paneb linnukese sisse:
`cb.checked = rule.days.includes(cb.value);`
* **`syncVisualToText` (UI-st andmetesse):** Iga kord, kui administraator paneb linnukese või muudab aega, käivitub see funktsioon. See loeb kõik UI read uuesti kokku. Käib läbi iga rea checkboxid, ja kui see on märgitud, lisab selle numbri stringi (nt `str += cb.value`). Lõpuks ehitab valmis uue JSON objekti ja kirjutab selle tagasi peidetud `<textarea>` sisse. API-le saadetakse alati see puhas JSON text.