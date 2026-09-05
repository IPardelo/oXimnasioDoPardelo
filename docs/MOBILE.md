# Compilar a app móbil (iOS / Android)

O Ximnasio do Pardelo distribúese en dous formatos a partir do mesmo código:

| | **Autoaloxado** (o modo por defecto deste repositorio) | **App móbil** (`VITE_MOBILE=1`) |
|---|---|---|
| Execútase | en calquera navegador, contra o teu propio servidor | nativamente en iPhone / Android (envoltorio Capacitor) |
| Contas | inicio de sesión con Google ou usuario/contrasinal, un perfil por persoa | ningunha — o móbil *é* a conta |
| Datos | sincronizados co teu servidor, lexibles desde o ordenador | quedan no dispositivo (ficheiro no almacenamento privado da app) |
| Recordatorios | Web Push desde o teu servidor | notificacións locais nativas, sen servidor implicado |
| Multimedia dos exercicios | servida polo teu servidor (`img/`, `gif/`) | cargada desde a CDN de jsDelivr |

O formato móbil nunca fala cun backend: sen pantalla de inicio de sesión, sen sincronización, sen
telemetría. O estado reflíctese desde `localStorage` a `ximnasioPardelo-state.json`, no
directorio de datos privado da app, en cada cambio (iOS pode desaloxar o almacenamento do WebView
baixo presión — o ficheiro espello é a copia durable e restáurase ao arrincar). As copias de
seguridade saen pola folla de compartir do sistema operativo en vez de por unha descarga do
navegador.

## Requisitos previos

- Node 20+
- **Android:** Android Studio (inclúe o SDK). Java 21 para Gradle.
- **iOS:** un Mac con Xcode 15+ e CocoaPods (`brew install cocoapods`). Un Apple ID gratuíto
  abonda para executar a app no teu propio iPhone (ver abaixo); a subscrición de pago só fai
  falta para a distribución na App Store, cousa que O Ximnasio do Pardelo non fai.

## Compilar e executar

```sh
cd frontend
npm install
npm run build:mobile        # compilación VITE_MOBILE + `cap sync` en android/ e ios/

npx cap open android        # abre Android Studio → executa nun emulador ou dispositivo
npx cap open ios            # abre Xcode (só Mac) → configura o teu equipo de sinatura e executa
```

`npm run build:mobile` incrusta a base de medios da CDN no paquete e copia a compilación web
dentro dos dous proxectos nativos — execútao de novo despois de cada cambio no código web, antes
de compilar de xeito nativo.

> **Aviso:** despois de `build:mobile`, `frontend/dist` contén o paquete *móbil*.
> Executa un `npm run build` normal outra vez antes de despregar `dist` nun servidor.

## Iconas e pantallas de arranque

`frontend/resources/icon.svg` é a orixe a 1024×1024 (o glifo da mancorna sobre o fondo da app).
Xera todos os recursos por plataforma a partir del nun computador coas ferramentas necesarias:

```sh
cd frontend
npx @capacitor/assets generate --iconBackgroundColor '#0c0e12' --splashBackgroundColor '#0c0e12'
```

(Se o xerador non acepta o SVG directamente, expórtao antes a `resources/icon.png` a 1024×1024 —
calquera ferramenta de imaxe pode facelo.)

## Distribución — deliberadamente sen tendas de apps

A app móbil de O Ximnasio do Pardelo non está na Play Store nin na App Store, e iso é unha
decisión: sen contas de tenda, sen regras de tenda, sen cotas anuais entre ti e unha app de código
aberto.

### Android — instalación manual do APK

Compila e asina o teu propio APK (pasos abaixo), e logo instálao directamente no móbil. Android
pídeche a primeira vez que permitas instalacións desde o navegador/xestor de ficheiros — é o
habitual para calquera app fóra da Play Store.

Para compilar e asinar o teu propio:

```sh
cd frontend && npm run build:mobile
cd android && ./gradlew assembleRelease            # → app/build/outputs/apk/release/app-release-unsigned.apk

# unha soa vez: crea un keystore. GÁRDAO — as actualizacións deben asinarse coa mesma clave,
# ou Android rexeitará instalar a nova versión por riba da anterior.
keytool -genkeypair -keystore my.keystore -alias ximnasioPardelo -keyalg RSA -validity 10950

# aliñar + asinar (zipalign/apksigner veñen coas build-tools do SDK de Android)
zipalign -f -p 4 app-release-unsigned.apk aligned.apk
apksigner sign --ks my.keystore --ks-key-alias ximnasioPardelo --out ximnasioPardelo.apk aligned.apk
```

### iPhone — o que é posible de verdade

Apple non permite instalar apps fóra da App Store, así que non hai unha descarga `.ipa` que se
instale sen máis. As túas opcións gratuítas:

- **Autoalóxao + PWA** (recomendado): abre a túa instancia en Safari → Compartir → *Engadir á
  pantalla de inicio*. App a pantalla completa, sen caducidade, ademais de sincronización e
  inicio de sesión.
- **Sinatura gratuíta de Xcode:** abre `ios/` en Xcode co teu Apple ID gratuíto como equipo e
  execútaa no teu propio iPhone. Apple caduca a sinatura aos 7 días; volve executar desde Xcode
  para renovala.
- **AltStore:** automatiza esa re-sinatura de 7 días por Wi-Fi mediante unha app complementaria
  no Mac.

### Notas de publicación para quen manteña o proxecto

- Incrementa `versionName`/`versionCode` en `android/app/build.gradle` en cada versión; manténo
  en paralelo con `frontend/package.json`. `versionCode` ten que aumentar estritamente ou as
  actualizacións non se instalarán por riba dun APK existente.
- **Licenza:** O Ximnasio do Pardelo é AGPL-3.0, o que por si só encaixa mal cos termos de
  servizo das tendas de apps. `NOTICE.md` inclúe unha excepción para tendas de apps (un permiso
  adicional baixo o AGPL §7) concedida polo titular dos dereitos — relevante só se algún día chega
  a distribuírse nunha tenda.
- A app só pide permiso de notificacións cando se activa o recordatorio do día de adestramento, e
  (en Android) declara `SCHEDULE_EXACT_ALARM` para que o recordatorio se dispare ao minuto exacto
  onde o usuario o permita.
