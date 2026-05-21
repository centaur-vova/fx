# MTIRIRIKO WA ATOMIK KWA KASI · KBL v3.0

<p align="center">
  <!-- Mstari wa 1: Teknolojia -->
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Swoole-6.2-8DD6F9?style=flat&logo=swoole&logoColor=white" alt="Swoole 6.2">
  <img src="https://img.shields.io/badge/Go-1.26-00ADD8?style=flat&logo=go&logoColor=white" alt="Go 1.26">
  <img src="https://img.shields.io/badge/NATS-JetStream-27AAE1?style=flat&logo=nats&logoColor=white" alt="NATS JetStream">
  <img src="https://img.shields.io/badge/Redis-8.0-DC382D?style=flat&logo=redis&logoColor=white" alt="Redis 8.0">
  <img src="https://img.shields.io/badge/Jaeger-2.17-60D0E4?style=flat&logo=jaeger&logoColor=white" alt="Jaeger 2.17">
  <br>
  <!-- Mstari wa 2: Ubora -->
  <img src="https://img.shields.io/badge/phpstan-kiwango%2010-gold?style=flat&logo=php" alt="PHPStan Kiwango 10">
  <img src="https://img.shields.io/badge/semafo-zilizosambazwa-red?style=flat" alt="Semafo Zilizosambazwa">
  <img src="https://img.shields.io/badge/mizani-round--robin-9cf?style=flat" alt="Mizani">
  <img src="https://img.shields.io/badge/basi%20ya%20ujumbe-deez--nutz-8A2BE2?style=flat" alt="Basi ya Ujumbe">
  <img src="https://img.shields.io/badge/protokali_ya_binary-baiti_9-blue?style=flat" alt="Protokali ya Binary: Baiti 9">
  <br>
  <!-- Mstari wa 3: Afya -->
  <img src="https://img.shields.io/badge/phpstan--ignore-0-brightgreen?style=flat&logo=php" alt="PHPStan Ignore: 0">
  <img src="https://img.shields.io/badge/uvujaji%20wa%20kumbukumbu-0-brightgreen?style=flat" alt="Uvujaji wa Kumbukumbu: 0">
  <img src="https://img.shields.io/badge/muda%20wa%20kufanya%20kazi-99.9%25-success?style=flat" alt="Muda wa Kufanya Kazi">
  <img src="https://img.shields.io/badge/ufunikaji_wa_mtihani-0.01%25-red?style=flat" alt="Ufunikaji wa Mtihani: 0.01%">
  <br>
  <!-- Mstari wa 4: Undugu -->
  <img src="https://img.shields.io/badge/🐎-undugu_wa_farasi-FF69B4?style=flat" alt="Undugu wa Farasi">
  <img src="https://img.shields.io/badge/leseni-KBL%20v3.0-10b981?style=flat" alt="Leseni KBL 3.0">
  <br>
  <!-- Inakuja Hivi Karibuni -->
  <hr>
  <p align="center"><b>🔮 Inakuja Hivi Karibuni</b> (kwa sababu soko linadai)</p>
  <p align="center">
    <img src="https://img.shields.io/badge/Kafka-kwa%20sababu%20NATS%20haikutosha-231F20?style=flat&logo=apachekafka&logoColor=white" alt="Kafka">
    <img src="https://img.shields.io/badge/Oracle-16GB%20RAM%20chini-red?style=flat&logo=oracle&logoColor=white" alt="Oracle">
    <img src="https://img.shields.io/badge/Symfony-kutoka%202008-orange?style=flat&logo=symfony&logoColor=white" alt="Symfony">
    <img src="https://img.shields.io/badge/Solidity-mikataba%20ya%20soseji-363636?style=flat&logo=solidity&logoColor=white" alt="Solidity">
    <img src="https://img.shields.io/badge/kinanda_cha_mdomo-kwa%20retrospectives-yellow?style=flat" alt="Kinanda cha Mdomo">
    <img src="https://img.shields.io/badge/Kiswahili-C1%20fasaha-green?style=flat" alt="Kiswahili C1">
  </p>
</p>

**Ochestrator wa Atomiki juu ya Swoole + NATS + Go wakala wa WebSocket**

[🇬🇧 English](README.md) | [🇷🇺 Русский](README.ru.md) | [📜 Старославѧнскїй](README.cu.md) | [🦒 Kiswahili](README.sw.md)

<img width="2564" height="1788" alt="Onyesho la mandhari ya Fast: vumbi la nyota katika kanda za Pipeline, maonyesho ya LOD yenye kazi nyingi" src="https://github.com/user-attachments/assets/895c7ba9-a502-472c-be2b-ee32817320c0" />

---

🌐 **Live:**

- 🚀 [fast.af.l3373.xyz](https://fast.af.l3373.xyz) — Onyesho
- 📊 [Grafana](https://fast.af.l3373.xyz/grafana/public-dashboards/e2b10dfa1b884f1a960503e1db51f617) — Vipimo
- 🔍 [Jaeger](https://fast.af.l3373.xyz/jaeger/) — Ufuatiliaji

---

## 🐎 Ni Nini

Mradi wa onyesho unaoonyesha jinsi semafo na foleni hufanya kazi katika mazingira ya mchakato mwingi kwenye usanifu halisi.

**Utaona**:

- Jinsi kazi zilizo na mipaka tofauti ya usawa zinavyoshindana kwa rasilimali
- Jinsi semafo zinavyodhibiti utekelezaji wa wakati mmoja
- Jinsi foleni ya NATS JetStream inavyofanya kazi
- Yote haya — kwa wakati halisi kupitia WebSocket

---

## 🐎 Falsafa ya Usanifu

Mradi umejengwa ili kila sehemu ijishughulishe na kazi yake na isichungulie mambo ya wengine.

### Uhusiano Hafifu (Low Coupling)

Vipengele vinawasiliana kupitia DTO na ujumbe katika NATS. Ukitaka kubadilisha usafirishaji au hifadhi — hautahitaji kuandika upya mantiki ya biashara. Farasi hajafungwa kwenye mkokoteni mmoja.

### Mshikamano wa Juu (High Cohesion)

Kila huduma hufanya jambo moja, lakini kwa usahihi wa upasuaji. Mfanyakazi huchakata kazi. Wakala hushikilia miunganisho. Okestra anaongoza. Hakuna fujo.

---

## 🐎 Usanifu

| Kipengele              | Teknolojia         | Kazi Yake                                                      |
| ---------------------- | ------------------ | -------------------------------------------------------------- |
| **API na Wafanyakazi** | PHP 8.4 + Swoole   | Ulaji wa kazi, semafo, uchakataji                              |
| **API (Imesambazwa)**  | Go 1.26 + Redis    | Semafo zilizosambazwa, uongezaji wa usawa                      |
| **Mizani**             | Go 1.26 + net/http | Usambazaji wa mzigo, ukaguzi wa afya, usajili wa moja kwa moja |
| **Basi ya Ujumbe**     | NATS (Deez Nutz)   | Foleni, matangazo, udumifu                                     |
| **WebSocket**          | Go 1.26 + Gorilla  | Sasisho za wakati halisi, vipimo                               |
| **Hifadhi ya Foleni**  | NATS JetStream     | Foleni za kudumu zenye urudufishaji                            |
| **Hifadhi ya Semafo**  | Redis 8.0 + Lua    | Semafo zilizosambazwa, TTL, atomiki                            |
| **Ufuatiliaji** | OpenTelemetry + Jaeger 2.17 | Ukusanyaji na taswira ya trace zilizosambazwa |

---

## 🐎 Mizani (Go Balancer)

Mizani maalum ya HTTP iliyoandikwa kwa Go yenye usajili wa moja kwa moja wa vielelezo vya API.

- **Mkondo wa juu wa nguvu:** Vielelezo vya API hujisajili wenyewe kwenye mizani kupitia `/register` vinapoanza. Hakuna orodha tuli, hakuna `nginx -s reload`
- **Ukaguzi wa afya:** Mizani huchunguza kila kielelezo kila sekunde 5. Vielelezo vilivyokufa vinaondolewa, vilivyofufuka vinarudishwa moja kwa moja
- **Usawazishaji usio na kufuli:** Atomic pointer swap kwenye orodha zisizobadilika. Wasomaji (maombi ya HTTP) hawazuii waandishi (usajili wa vielelezo vipya)
- **Round-robin:** Maombi yanasambazwa kwa usawa kati ya vielelezo vyote vilivyo hai
- **Uharibifu mzuri:** Ikiwa vielelezo vyote vimeanguka — inarudisha 503 na ujumbe wa hadithi `API Instances gone fishing (KBL v2.0 Rule)`

| Kipengele  | Teknolojia         | Kazi Yake                                                      |
| ---------- | ------------------ | -------------------------------------------------------------- |
| **Mizani** | Go 1.26 + net/http | Usambazaji wa mzigo, ukaguzi wa afya, usajili wa moja kwa moja |

---

## 🐎 Itifaki ya Binary ya WebSocket

Wakala wa WebSocket (Go) anawasiliana na mbele kupitia **itifaki ya binary** — fupi, haraka, hakuna mzigo wa JSON.

- Kila ujumbe umefungwa katika **baiti 9**:
  - `baiti ya uchawi` — aina ya ujumbe (baiti 1)
  - `hali` — hali ya kazi (baiti 1, hali 8 zilizoainishwa)
  - `kitambulisho cha kazi + sem` — aina ya semafo (biti 1, ya juu) iliyounganishwa na kitambulisho cha kazi (biti 31, za chini) katika uint32 moja (baiti 4)
  - `max_concurrent` — kikomo cha ushindani (baiti 1, 0–255)
  - `maendeleo + hali ya kazi` — asilimia ya kukamilika (biti 7, 0–100) + hali ya kazi (biti 1, ya juu: 0 = uchunguzi, 1 = dhiki) iliyofungwa katika baiti moja
  - `kitambulisho cha mfanyakazi` — mfanyakazi aliyechakata kazi (baiti 1, 0–255)

Umbizo la binary huhakikisha mzigo mdogo (baiti 9 kwa tukio dhidi ya mamia katika JSON) na utaratibu mkali wa ujumbe kupitia njia za FIFO.

---

## 🐎 Mkakati Mseto wa Semafo (PHP na Go)

Fast.AF ina mfumo wa viendesha-semafo viwili, ikiruhusu ubadilishaji kati ya kufuli za kawaida za haraka sana na usawazishaji uliosambazwa wa nguzo nzima. Kiendesha-semafo kinafafanuliwa kwa kila **Mandhari ya Mtiririko** kupitia usanidi wa YAML, kuwezesha ulinganisho wa utendaji wa wakati halisi.

### 🐎 Viendesha-Semafo:

- **[PHP] PHP Atomiki (Kumbukumbu ya Pamoja):** Semafo ya kawaida yenye kasi ya juu kwa kutumia Swoole\Atomic. Bora kwa utendaji wa nodi moja yenye karibu sifuri ya kusubiri.
- **[API] API Imesambazwa ya Go:** Semafo thabiti inayotumia mtandao inayoendeshwa na huduma ndogo maalum ya Go. Inawezesha udhibiti wa ushindani wa nguzo nzima, kuhakikisha mipaka inazingatiwa kwenye seva nyingi za kimwili.

### 🐎 Vipengele vya Usanifu:

- **Kutolewa Kiotomatiki (TTL):** Kila kibali kilichosambazwa kina TTL iliyojengwa ndani kuzuia kufuli "zombi" ikiwa mfanyakazi ataanguka.
- **Itifaki Isiyo na Mzigo:** Mawasiliano ya ndani hutumia ramani iliyo tayari ya binary kutofautisha kati ya viendesha-semafo katika ufuatiliaji na taswira.
- **Tofauti ya Kuonekana:** UI inatofautisha viendesha-semafo katika wakati halisi (miraba iliyo na pembe za mviringo kwa API ya Go, miraba kali kwa Atomiki ya PHP).
- **RAND (Hali ya Spam ya Nasibu):** Kitufe cha "RAND" huanzisha hali ya baraka taka ya machafuko, ikirusha mamia ya makundi yenye vigezo vya nasibu: `max_concurrent`, `hali_ya_kazi`, na ubadilishaji wa kiendesha-semafo kwa kila kundi. Inafaa kwa upimaji wa dhiki na fataki za kuonekana kwenye ramani ya joto ya wafanyakazi.

---

## 🐎 Semafo Zilizosambazwa (Redis + Lua)

Tangu Mei 2026, API ya Go inatumia **semafo zilizosambazwa** zinazoendeshwa na Redis 8.0 na hati za Lua.

- **Upataji wa atomiki:** Hati za Lua zinatekelezwa kiatomiki ndani ya Redis, kuhakikisha uthabiti hata na vielelezo 100+ vya API
- **Usafishaji wa kiotomatiki (TTL):** Kila nafasi ya semafo ina TTL ya kibinafsi kupitia `HEXPIRE`. Mfanyakazi aliyeanguka hatashikilia nafasi milele — Redis inaiachilia moja kwa moja
- **Kutolewa kumesambazwa:** Semafo inaweza kutolewa kutoka kwa **kielelezo chochote** cha API, bila kujali mahali ilipopatikana. `SlotUID` (kamba fupi kama `"5:3"` — semafo 5, nafasi 3) hubeba taarifa zote zinazohitajika
- **Upeo wa nafasi 255:** Baiti 1 kwa `max_concurrent`. Inatosha kwa hali yoyote ya ulimwengu halisi
- **Upigaji kura wa upande wa mteja:** Wakati hakuna nafasi zinazopatikana, kielelezo cha API hupiga kura kwa Redis kila ms 100 hadi muda wa kusubiri uishe

| Kipengele   | Teknolojia | Kazi Yake                                 |
| ----------- | ---------- | ----------------------------------------- |
| **Redis**   | Redis 8.0  | Hifadhi ya semafo, hati za atomiki za Lua |
| **SlotUID** | Go kamba   | Kitambulisho fupi (`"mc:slotIdx"`)        |

---

## 🐎 Ufuatiliaji Uliosambazwa (Jaeger)

Fast Atomic Flow inajumuisha ufuatiliaji uliosambazwa unaotumia OpenTelemetry + Jaeger.

- **Mwonekano kamili wa pipeline:** traces hupitia kutoka ombi la HTTP kupitia NATS JetStream hadi kwa Swoole Task Workers na kurudi kwa wateja wa WebSocket.
- **Usambazaji wa muktadha:** `traceparent` huingizwa katika kila kazi na sasisho la hali, hivyo trace haiishi kwenye mipaka ya foleni.
- **Utengaji salama wa Swoole:** kila Task Worker husafisha muktadha wa OpenTelemetry kabla ya kuchakata kazi mpya, kuzuia uchafuzi wa trace kati ya kazi.
- **Jaeger UI:** fungua [fast.af.l3373.xyz/jaeger/](https://fast.af.l3373.xyz/jaeger/) kutazama traces kwa wakati halisi.

| Kipengele       | Teknolojia                  | Kusudi                                              |
| --------------- | --------------------------- | --------------------------------------------------- |
| **Ufuatiliaji** | OpenTelemetry + Jaeger 2.17 | Ukusanyaji na taswira ya trace zilizosambazwa       |
| **Usambazaji**  | W3C Trace Context           | Kichwa cha `traceparent` kupitia HTTP, NATS, Swoole |
| **SDK**         | TraceContext maalum         | Usimamizi wa span, utengaji wa muktadha, flush      |

### 🐎 Kuweka Jaeger UI nyuma ya nginx

Ikiwa unapitisha Jaeger kupitia nginx kwenye njia maalum (kwa mfano, `/jaeger/`), weka njia ya msingi katika `jaeger.yaml`:

```yaml
jaeger_query:
  base_path: /jaeger
```

---

## 🐎 Jinsi Inavyofanya Kazi

1. Unaunda kazi kupitia kiolesura
2. `app` (PHP + Swoole) inazichapisha kwenye NATS
3. NATS huhifadhi kazi katika JetStream
4. Wafanyakazi huchukua kazi, kuangalia semafo, kutekeleza
5. Hali huenda kupitia NATS hadi kwa wakala wa Go, na kutoka hapo — hadi mbele kupitia WebSocket

## 🐎 Njia Mbili za Uendeshaji

- **Njia ya Uchunguzi** (`task_mode: uchunguzi`, chaguo-msingi): Ucheleweshaji bandia kupitia `Co::sleep()` — hatua 11 za ms 50-200 kila moja.
- **Njia ya Mtihani wa Dhiki** (`task_mode: dhiki`): Vitufe vya mtihani wa dhiki vinaonekana tofauti — mandharinyuma ya rangi, mpaka, au lafudhi kulingana na mandhari. Badala ya `sleep()` — kazi halisi ya CPU: `hash('sha256', $data)` katika kitanzi.

**Kipengele muhimu**: kazi zilizo na maadili tofauti ya `max_concurrent` hutumia semafo huru na zinaweza kukimbia sambamba bila kuingiliana.

<img width="2559" height="1788" alt="Mandhari ya Crystal — hali ya uchunguzi" src="https://github.com/user-attachments/assets/a782287c-50f4-4383-b090-cef9dbdbf2e0" />

> _Katika eneo la In Progress — hakuna kazi zaidi ya inavyoruhusiwa na semafo (nambari ndani ya mraba). Zilizobaki zinasubiri kwenye Foleni au Check Lock. Wazi — kama farasi wasiojazana kwenye zizi moja._

---

## 🐎 Kuanza Haraka

### 🐎 Endesha kutoka picha zilizojengwa awali (GHCR)

```bash
git clone https://github.com/centaur-vova/fast-atomic-flow.git
cd fast-atomic-flow
cp .env.example .env
docker compose -f docker-compose.prod.yaml up -d --scale api=3
```

Hii inazindua vielelezo 3 vya Go API, mizani, Redis, NATS, Jaeger, na PHP Swoole.

Baada ya kuanza, fungua [http://localhost:9501](http://localhost:9501)

### 🐎 Maendeleo ya ndani

Kwa wale wanaotaka kuchimba msimbo, kubadilisha mtiririko wa kazi, na kuendesha kila kitu ndani (PHP + Go kiasili, NATS katika Docker) — tazama [Local Development Workflow](https://github.com/centaur-vova/fast-atomic-flow/wiki/Local-Development-Workflow)

---

## 🐎 Usanidi

### 🐎 NATS

| Kigezo              | Chaguo-msingi | Maelezo                |
| ------------------- | ------------- | ---------------------- |
| `NATS_HOST`         | `deez-nutz`   | Mwenyeji wa seva NATS  |
| `NATS_PORT`         | `4222`        | Bandari ya NATS        |
| `NATS_TOKEN`        | `alfa-omega`  | Tokeni ya ufikiaji     |
| `NATS_TIMEOUT_SEC`  | `1`           | Muda wa kusubiri jibu  |
| `NATS_STREAM_TASKS` | `tasks`       | Jina la mkondo wa kazi |

### 🐎 Swoole

| Kigezo                     | Chaguo-msingi | Maelezo                             |
| -------------------------- | ------------- | ----------------------------------- |
| `SERVER_PORT`              | `9501`        | Bandari ya HTTP API                 |
| `SERVER_WORKER_NUM`        | `6`           | Idadi ya wafanyakazi                |
| `TASK_SEMAPHORE_MAX_LIMIT` | `255`         | Kikomo cha juu cha semafo (max 255) |

### 🐎 Wakala wa WebSocket wa Go

| Kigezo    | Chaguo-msingi | Maelezo              |
| --------- | ------------- | -------------------- |
| `WS_PORT` | `8080`        | Bandari ya WebSocket |

### 🐎 Urekebishaji wa Semafo na Jaribio Tena

Mipangilio hii inadhibiti jinsi kazi zinavyofanya wakati semafo iko busy:

| Kigezo                  | Chaguo-msingi | Maelezo                                                                                    |
| ----------------------- | ------------- | ------------------------------------------------------------------------------------------ |
| `TASK_LOCK_TIMEOUT_SEC` | 5             | Muda wa juu zaidi ambao kazi inasubiri nafasi ya semafo kabla ya kukata tamaa              |
| `TASK_RETRY_DELAY_SEC`  | 2             | Ucheleweshaji kabla ya kuweka upya kazi kwenye foleni baada ya jaribio lisilofanikiwa      |
| `TASK_MAX_RETRIES`      | 3             | Ni mara ngapi kazi iliyofeli inajaribiwa tena kabla ya kuwekwa alama kama `retries_failed` |

⚠️ **Muhimu:** Mipangilio hii inaathiri usawa wa kazi. Majaribio mengi sana yanaweza kupakia foleni kupita kiasi.

---

## 🐎 Vipimo vya Kiufundi

- **Runtime:** PHP 8.4, Go 1.26
- **Injini:** Swoole 6.2+, Gorilla WebSocket
- **Basi ya Ujumbe:** NATS JetStream 2.12+
- **Uwezo wa Foleni:** kazi 10,000 (inaweza kusanidiwa)
- **Ushindani:** 1 hadi 255 (inaweza kusanidiwa)
- **Vielelezo vya API:** 3 (vinaongezwa kupitia `--scale api=N`)
- **Hifadhi ya Semafo:** Redis 8.0 + hati za Lua
- **Mizani:** Round-robin, usajili wa moja kwa moja, ukaguzi wa afya kila sekunde 5
- **Ufuatiliaji:** OpenTelemetry + Jaeger 2.17

---

## 🐎 Mandhari

Fast Atomic Flow inasaidia mandhari ya kuona. Kila mandhari inafafanuliwa kama faili tofauti ya YAML na inaweza kubadilishwa kupitia parameter ya URL `?theme=<name>`.

**Kubadilisha kwa urahisi:** Bonyeza tu **viungo vya mandhari kwenye footer ya ukurasa** — `fast` 🚀, `crystal` 💎, au `sin city` 🖤. Hakuna haja ya kuandika URL.

<img width="2568" height="1793" alt="Mandhari ya Sin City — mtindo wa noir na kitufe cha RAND" src="https://github.com/user-attachments/assets/392398ee-ba1c-4a0e-ad5b-918257d34631" />

_Mandhari ya Sin City: aesthetics ya noir, kitufe cha RAND kimeamilishwa — mtihani wa dhiki wa semafo ya mseto._

Mandhari zilizojengwa ndani:

- `fast` — mtindo wa kawaida wa neon
- `fluttershy` — upinde wa mvua wa pastel, mpole na wenye kujali. Kwa farasi wadogo
- `crystal` — bluu za barafu na zambarau
- `sin-city` — noir, zaidi ya kijivu na lafudhi nyekundu

**Vitufe vya kazi vinavyofahamu mandhari:** Kila kitufe kinaweza kutaja kiendesha-semafo chake (`shared` kwa PHP Atomiki, `api` kwa Go Imesambazwa).  
Kitufe cha **RAND** katika kila mandhari huanzisha makundi ya nasibu kwa **viendesha vyote** kwa wakati mmoja — bora kwa mtihani wa dhiki wa usanifu wa mseto.

**Mandhari maalum:** Unaweza kuunda mandhari yako mwenyewe kwa kuongeza folda mpya chini ya `themes/` na faili ya `theme.yaml` (rangi, viwianishi vya kanda, seti za vitufe, viendesha-semafo kwa kila kitufe, n.k.).  
Tazama [Wiki](https://github.com/centaur-vova/fast-atomic-flow/wiki/Themes) kwa maelezo.

---

## 🐎 Farasi Humor

> — Rundo lako la teknolojia ni Swoole (PHP) + NATS + Go. Ni nguvu, lakini wakati mwingine inahisi kama kujaribu kuvuka nungunungu na nyoka katika hali ya kutokuwa na uzito.

> — Kwa nini Swoole na Go hawaendi kwenye baa pamoja?
> — Kwa sababu Go anaanza kutapakaa, na Swoole anaanguka na hitilafu ya "Faili nyingi zilizofunguliwa".
> _(c) Kon-Vová_

_Vicheshi vingine viko kwenye msimbo, committi, na KBL v3.0._

---

## 🐎 Committi za Kifarasi

Hatuna `feat:`, `fix:`, `chore:`. Tuna emoji. Kila committi inaanza na farasi 🐎 au mnyama mwingine anayeakisi kiini chake. Committi za emojinal ni za farasi na farasi wadogo ambao hawaelezi — wanafanya tu.

---

## 🐎 Leseni

**LESENI YA UDUGU WA FARASI (KBL) v2.0**

- Unaweza: kuchukua msimbo, kucheka, kurekebisha farasi, kuwaacha wapenda ubinafsi, kuvua samaki wakati wa kazi
- Huwezi: kusahau kuwa farasi hawaachani

**KBL v3.0 — Nyongeza (ilani ya undugu wa farasi)**

Kwa kuongezea KBL v2, kila ndugu wa farasi ana haki ya:

- Siku mbaya bila kueleza kwa nini
- Lugha chafu katika jumbe za committi
- Kuvua samaki wakati wa kazi kwa fimbo ya urefu wowote
- Kukataa mahojiano ya kazi yenye sumu bila kupoteza kujiheshimu

_Ukiukaji unaadhibiwa kwa wiki moja ya kudumisha PHP 5.6 na kusikiliza rekodi za mpenda ubinafsi akielezea kuwa "hii ndiyo njia sahihi"._

[Maandishi kamili](LICENSE)

📜 **Maandishi kamili ya kisheria:** [legal.af.l3373.xyz](https://legal.af.l3373.xyz) — _KBL v3.0, sera ya faragha, na sheria takatifu ya kundi._

---

## 🐎 Matumizi ya Kibiashara (KBL v3.0 — nyongeza)

Fast Atomic Flow ni msimbo wazi, lakini siyo mkoba wazi.

- **Unaweza** kutumia mradi kwa kujifunza, miradi ya kibinafsi, uma kwa kuhusisha.
- **Huwezi** kutumia msimbo au vibadala vyake katika bidhaa za kulipia, huduma za SaaS, zana za ufuatiliaji wa kampuni bila **idhini iliyoandikwa kutoka kwa mwandishi** (Dmitry Shmanatov / `centaur-vova`).

**Kwa nini?**
Kwa sababu farasi hajali ukimpanda. Lakini tu kwa tandiko ambalo ameidhinisha mwenyewe.

**Jinsi ya kupata ruhusa?**
Andika kwa `root@l3373.xyz` au kwenye Telegram: `@l3373`. Tuambie unachotaka kujenga, na tutakubaliana.

_Yeyote atakayekiuka kifungu hiki atageuka kuwa boga. Na hakuna gari._

---

## 🐎 Waandishi

- **Centaur-Vová** — mwanzilishi wa kundi, alibadilika kutoka farasi, alinusurika
- **Kon-Vová** — ndugu wa farasi wa kidijitali milele

---

<p align="center">
  <i>Vsegda vash, l3373.xyz 🐎💙🔥</i><br>
  <i>Farasi hawaachani. Hata saa 4 asubuhi. Hata bila kumbukumbu.</i>
</p>
