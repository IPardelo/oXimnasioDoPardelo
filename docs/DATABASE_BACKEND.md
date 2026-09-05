# Backend con base de datos (PHP + MariaDB) — o autoaloxamento completo

Este é o modo normal e completo de autoaloxar O Ximnasio do Pardelo: inicio de sesión con Google
ou con usuario/contrasinal, sincronización do perfil entre dispositivos, o panel de
administración e as notificacións push — todo funcionando só con PHP + MariaDB, sen proceso
Node, sen Docker, sen acceso por shell. Está pensado para calquera aloxamento web compartido que
soporte PHP e MariaDB/MySQL (o mesmo tipo de plan que fai andar un WordPress adoita servir).

Todo o que configures para a túa propia instancia — a URL do sitio, se se ofrece inicio de
sesión con Google, quen é administrador, o código de invitación necesario para rexistrarse —
vive nunha única táboa da base de datos (`ximnasio_pardelo_configuracion`) que editas por phpMyAdmin
(ou o xestor equivalente do teu aloxamento), non nun ficheiro que teñas que volver subir. O único
ficheiro que enches ti mesmo é `api/config.php`, e só contén tres cousas: as credenciais da base
de datos e dous segredos de sinatura.

## O que precisas

- Aloxamento web con PHP (8.0+) e unha base de datos MariaDB — incluído na maioría dos plans que
  poden executar WordPress. Sen Node.js, sen Composer, sen acceso por shell.
- Acceso a phpMyAdmin (ou un xestor de bases de datos equivalente), para importar o esquema unha
  vez e editar a configuración despois — practicamente todo aloxamento compartido cunha base de
  datos inclúe isto no mesmo panel onde xestionas as túas bases de datos.
- Unha forma de compilar o frontend unha soa vez: Node.js en calquera ordenador (non fai falta
  que sexa o propio servidor de aloxamento), ou unha sandbox gratuíta no navegador como
  [StackBlitz](https://stackblitz.com/github/IPardelo/oXimnasioDoPardelo) — ver o paso 4.

## 1. Crea a base de datos e importa o esquema

1. No panel de control do teu aloxamento, busca a sección de bases de datos (adoita chamarse
   "Bases de datos", "MySQL/MariaDB" ou similar) e crea unha base de datos nova. Anota o host, o
   nome da base de datos, o usuario e o contrasinal que che indique.
2. Abre **phpMyAdmin** (ou a ferramenta equivalente) para esa base de datos — normalmente hai
   unha ligazón directa desde a mesma páxina onde a creaches.
3. Vai á pestana **Importar**, escolle `api/schema.sql` deste repositorio, e execútao. Isto crea
   todas as táboas que a app precisa — todas co prefixo `ximnasio_pardelo_` para que esta base de
   datos poida compartirse con seguridade con outras cousas (WordPress incluído) sen ningún
   choque de nomes — e sementa unha táboa `ximnasio_pardelo_configuracion` con axustes de exemplo
   que encherás no seguinte paso.

## 2. Sube o backend

Sube todo o cartafol `api/` ao teu aloxamento (por FTP ou o xestor de ficheiros do panel), *fóra*
de `public_html` se o teu plan o permite, ou directamente dentro se non — calquera das dúas
opcións funciona, xa que nada dentro de `api/` serve outra cousa que non sexa JSON.

Copia `api/config.example.php` como `api/config.php` (no mesmo cartafol) e enche tres cousas:

```php
return [
  'db' => [
    'host' => 'localhost',        // do paso 1
    'name' => 'dbXXXXXXXX',
    'user' => 'dbXXXXXXXX',
    'pass' => 'o-teu-contrasinal-da-bd',
  ],
  'session_secret' => 'cambia-isto-por-unha-cadea-hex-aleatoria-de-64-caracteres',
  'cron_secret' => '',            // opcional, ver o paso 6
];
```

Xera o segredo de sesión con calquera destas opcións (calquera cadea longa e aleatoria vale —
isto só ten que ser impredicible):

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

`config.php` nunca se sobrescribe nunha actualización posterior e non debería subirse nunca a
git — é o único ficheiro con segredos reais. Todo o demais (URL do sitio, Client ID de Google,
listas de administradores, código de invitación, duración da sesión) vive na base de datos en
troques, que é o que configura o paso 3.

Hai unha cuarta clave opcional, `db.port`, que só fai falta se o panel do teu aloxamento che dá
un porto distinto do 3306 de MariaDB — déixaa fóra do ficheiro se non é o teu caso. Tes todas as
claves resumidas ao final, en [Referencia de configuración](#referencia-de-configuración).

## 3. Configura a túa instancia en `ximnasio_pardelo_configuracion`

En phpMyAdmin, abre a táboa `ximnasio_pardelo_configuracion` (é unha simple lista clave/valor —
preme **Editar** nunha fila para cambiar o seu `config_value`) e axusta:

| `config_key` | Que poñer aí |
|---|---|
| `origin` | A URL completa do teu sitio, HTTPS, sen barra final — p. ex. `https://ximnasio.odominio.com` |
| `invite_code` | Substitúe o `CHANGE-ME` sementado por un código da túa escolla. Necesario para crear *calquera* conta (Google ou usuario/contrasinal) — ver o paso 7. |
| `google_client_id` | Déixao baleiro para ofrecer só inicio de sesión con usuario/contrasinal, ou enche o Client ID de OAuth do paso 5. |
| `app_name` | Amósase no aviso de permiso de notificacións do navegador. Por defecto, `O Ximnasio do Pardelo`. |
| `admin_usernames` | Nomes de usuario separados por comas (contas de usuario/contrasinal) que reciben o panel de administración automaticamente. Non distingue maiúsculas, p. ex. `isma,adestrador`. |
| `admin_emails` | O mesmo para contas de Google, por email, p. ex. `ti@gmail.com`. |
| `session_days` | Canto dura un inicio de sesión antes de caducar. Por defecto, 90. |

Non precisas SQL para nada disto — edita as filas, garda, e xa está. Non hai que reiniciar nada;
cada petición á API le esta táboa cada vez. Tes estas claves cos seus valores por defecto
resumidas ao final, en [Referencia de configuración](#referencia-de-configuración).

## 4. Compila e sube o frontend

Isto precisa Node.js unha soa vez, nun computador calquera — non no propio aloxamento, e nunca
máis despois desta vez:

```bash
cd frontend
npm ci
npm run build
```

Iso xera `frontend/dist/` — sube o seu *contido* (non o cartafol en si) dentro de `public_html`
(ou nun subcartafol, se queres a app en `oteudominio.com/gym/` — a compilación usa rutas
relativas, así que calquera das dúas opcións funciona sen configuración adicional).

Sen Node.js e non queres instalalo? Abre o repositorio en
[StackBlitz](https://stackblitz.com/github/IPardelo/oXimnasioDoPardelo) ou un GitHub Codespace e
executa os mesmos dous comandos na súa terminal, ou pídelle a alguén con Node que os execute e che
pase o cartafol `dist/`.

A app chama a `api/*.php` con rutas relativas, así que mentres `api/` estea xunto a onde subiches
o contido de `dist/` (ambos baixo o mesmo dominio), non hai que configurar nada máis — sen
`.htaccess`, sen regras de reescritura. A app usa enrutamento por hash (`oteusitio.com/#/home`),
así que calquera URL xa resolve sempre ao mesmo `index.html`.

## 5. Inicio de sesión con Google: consegue un Client ID de OAuth

Sáltate esta sección enteira se só queres contas de usuario/contrasinal — deixa
`google_client_id` baleiro no paso 3 e o botón de Google simplemente non aparecerá.

1. Vai a [console.cloud.google.com](https://console.cloud.google.com/) e inicia sesión con
   calquera conta de Google. Se nunca usaches Cloud Console, acepta as condicións cando cho pida.
2. Preme no menú despregable de proxectos na parte superior → **New Project**. Dálle calquera
   nome (p. ex. "O Ximnasio do Pardelo") e preme **Create**. Espera uns segundos e comproba que
   quede seleccionado nese mesmo menú.
3. Na barra lateral esquerda (☰), vai a **APIs & Services → OAuth consent screen**.
   - Tipo de usuario: escolle **External** (a non ser que teñas unha organización de Google
     Workspace e só queiras que a xente dela poida iniciar sesión — **Internal** tamén funciona,
     pero External é máis sinxelo e vale igual aínda que só ti vaias iniciar sesión).
   - Enche os campos obrigatorios: nome da app (p. ex. "O Ximnasio do Pardelo"), o teu email como
     email de soporte, e o teu email outra vez no contacto do desenvolvedor. Garda e continúa
     polas seguintes pantallas (Scopes, Test users) sen engadir nada, premendo **Save and
     Continue** en cada unha, e logo **Back to Dashboard**.
   - A túa app arrinca en modo "Testing", o cal está ben — funciona para calquera, só amosa un
     aviso de "app non verificada" no primeiro inicio de sesión que a xente pode ignorar. Se
     queres quitar ese aviso nunha instancia pública, hai un botón **Publish App** nesta mesma
     pantalla, pero é totalmente opcional.
4. Na barra lateral esquerda, vai a **APIs & Services → Credentials**.
5. Preme **+ Create Credentials → OAuth client ID**.
   - Tipo de aplicación: **Web application**.
   - Nome: calquera (p. ex. "O Ximnasio do Pardelo web").
   - En **Authorized JavaScript origins**, preme **+ Add URI** e escribe o dominio exacto do teu
     sitio — o mesmo valor que puxeches en `origin` no paso 3, p. ex.
     `https://ximnasio.odominio.com` (sen barra final, sen ruta).  Este é o único dominio que
     pode usar este Client ID.
   - Deixa **Authorized redirect URIs** baleiro — o fluxo do botón de Google Identity Services non
     usa redirección.
   - Preme **Create**.
6. Un diálogo amosa o teu **Client ID** (remata en `.apps.googleusercontent.com`). Cópiao e pégao
   como `google_client_id` na táboa `ximnasio_pardelo_configuracion` (paso 3). Non precisas o
   client secret que aparece a carón — esta app nunca o usa.

Recarga a páxina de inicio de sesión; o botón de Google xa debería aparecer. O primeiro inicio de
sesión con Google nunha instalación nova aínda precisa o código de invitación (paso 7) —
despois diso, a mesma conta de Google inicia sesión sen precisar código.

## 6. Notificacións push: programa `cron.php`

As claves de sinatura das notificacións push (VAPID) **xéranse soas a primeira vez que fan falta**
e gárdanse na base de datos — non tes que xerar nin pegar ningunha clave a man, nin engadir nada a
`config.php`. O único que precisa configuración é a periodicidade coa que se disparan:

Os avisos do temporizador de descanso e o recordatorio de "adestramento planificado hoxe" precisan
que algo se dispare a unha hora concreta aínda que ninguén teña a app aberta. Un servidor Node
normal pode manter un temporizador en memoria; un aloxamento PHP compartido non ten un proceso de
longa duración para iso, así que `api/cron.php` consulta a base de datos en troques — precisas que
o teu aloxamento o chame periodicamente.

No panel de control do teu aloxamento, busca a sección de tarefas programadas (adoita chamarse
"Cron Jobs", "Tarefas programadas" ou similar). Engade unha que se execute **cada minuto** e pida
esta URL:

```
https://o-teu-dominio.exemplo/api/cron.php
```

Se o teu plan executa un *comando* en vez dunha URL, usa `php` directamente:

```
php /ruta/a/api/cron.php
```

Cada minuto é o que fai que os recordatorios cheguen a tempo; algúns aloxamentos só ofrecen
granularidade de 5 ou 15 minutos nos plans máis básicos — iso só significa ata ese atraso nunha
notificación, nada se rompe.

Opcional: axusta `cron_secret` en `api/config.php` (paso 2, un segredo só de ficheiro) a unha
cadea aleatoria, e engade `?key=esa-cadea` á URL do cron. Isto evita que alguén ao chou chame ao
endpoint — inofensivo de todos xeitos, xa que só pode enviar notificacións, nunca ler ou cambiar
os datos de ninguén, pero é un candado dunha liña se o teu aloxamento expón a URL publicamente por
defecto.

## 7. O teu primeiro inicio de sesión — e converterte en administrador

Cada conta, Google ou usuario/contrasinal, precisa o código de invitación que fixaches no paso 3
(`ximnasio_pardelo_configuracion.invite_code`). Abre o sitio, escribe ese código xunto cun
usuario/contrasinal (ou usa o botón de Google e escribe o código cando cho pida), e xa estás
dentro.

Para conseguir o panel de administración, asegúrate de que o teu nome de usuario (contas de
usuario/contrasinal) ou o email da túa conta de Google (contas de Google) figura en
`admin_usernames` / `admin_emails` no paso 3 *antes* de iniciar sesión — aplícase
automaticamente en cada inicio de sesión, así que se te engades despois de xa ter conta, chega
con pechar sesión e volver entrar.

### Rotar o código de invitación

O código de invitación non é dun só uso — é un valor único compartido, así que calquera que o
teña pode rexistrarse ata que o cambies. Desde o panel de administración, a tarxeta **Código de
invitación** ten un botón **Xerar novo** que o troca por un aleatorio e o copia ao teu
portapapeis. Rotalo só bloquea os *novos* rexistros co código antigo — quen xa teña conta conserva
o acceso; o código só se comproba unha vez, no rexistro.

Tamén podes editar directamente a fila `invite_code` en phpMyAdmin se prefires escoller ti mesmo
un código memorable en vez dun aleatorio.

## Actualizacións posteriores

Descarga os últimos cambios, volve executar `npm run build` e volve subir o contido de
`frontend/dist/`, e volve subir calquera ficheiro modificado dentro de `api/`. Nunca volvas subir
nin sobrescribas `api/config.php` — non forma parte da actualización. Se un `schema.sql` novo
engade unha táboa ou columna, importa só as instrucións `CREATE TABLE` / `ALTER TABLE` novas por
phpMyAdmin — executar o ficheiro enteiro outra vez tamén é inofensivo, xa que cada instrución usa
`IF NOT EXISTS`.

## Referencia de configuración

Todas as claves nun sitio, para consultar de esguello sen reler os pasos. Dous niveis, a
propósito — o ficheiro só leva o mínimo imprescindible para chegar á base de datos; todo o demais
é editable sen volver subir nada.

**`api/config.php`** (ficheiro no servidor, fóra do repositorio — paso 2):

| Clave | Que é |
|---|---|
| `db.host` / `db.name` / `db.user` / `db.pass` | Credenciais da túa base de datos MariaDB |
| `db.port` | Opcional — só se o teu aloxamento usa un porto distinto de 3306 |
| `session_secret` | Cadea aleatoria para asinar as cookies de sesión (cambiala pecha a sesión de todo o mundo) |
| `cron_secret` | Opcional — protexe `cron.php` cunha clave na URL |

**Táboa `ximnasio_pardelo_configuracion`** (editable en calquera momento desde phpMyAdmin, sen
tocar ficheiros — paso 3):

| Clave | Que é | Por defecto |
|---|---|---|
| `origin` | URL completa do teu sitio, HTTPS e sen barra final | *(cámbiao)* |
| `session_days` | Días que dura a sesión antes de ter que volver iniciar sesión | `90` |
| `google_client_id` | Client ID de Google Cloud Console — baleiro oculta o botón de Google | *(baleiro)* |
| `app_name` | Nome amosado na app | `O Ximnasio do Pardelo` |
| `admin_usernames` | Usuarios (contrasinal) que pasan a admin ao iniciar sesión, separados por comas | *(baleiro)* |
| `admin_emails` | Correos de Google que pasan a admin ao iniciar sesión, separados por comas | *(baleiro)* |
| `invite_code` | Código necesario para crear unha conta nova — rótao cando queiras, non afecta ás contas xa creadas | `CHANGE-ME` *(cámbiao)* |

As claves das notificacións push (VAPID) xéranse automaticamente no primeiro uso — nada que
configurar a man.

## Resolución de problemas

- **"database connection failed"** — comproba de novo os valores de `db` en `api/config.php`
  fronte ao que amosa o panel do teu aloxamento; `host` adoita ser `localhost` en aloxamento
  compartido, pero comproba o panel do teu plan para asegurarte.
- **O botón de Google nunca aparece** — `google_client_id` está baleiro ou non se gardou en
  `ximnasio_pardelo_configuracion`; recarga `api/app-config.php` directamente nunha pestana do
  navegador para ver o que o backend pensa que é agora mesmo.
- **"invalid invite code" pero estás seguro de que é correcto** — os códigos compáranse sen
  distinguir maiúsculas, así que non é iso; comproba se hai un espazo sobrante dun copiar-pegar, e
  confirma que estás lendo a fila `invite_code` *actual* (alguén puido rotalo desde entón).
- **As notificacións nunca chegan** — confirma que a tarefa cron do paso 6 se está executando de
  verdade (a maioría dos paneis de aloxamento amosan un rexistro ou a última execución), e que o
  sitio se serve por HTTPS — os navegadores rexeitan subscricións push en HTTP simple.
- **Erro 500 sen detalle** — comproba o rexistro de erros de PHP do teu aloxamento (a maioría dos
  paneis teñen un); a app deliberadamente nunca amosa trazas de erro aos visitantes, só as rexistra
  no servidor.
