# Aloxamento estático (sen servidor de ningún tipo)

O modo normal de autoaloxamento de O Ximnasio do Pardelo precisa do backend PHP + MariaDB en
`api/` — é do que dependen o inicio de sesión con Google/usuario, a sincronización entre
dispositivos, o panel de administración e as notificacións push (ver
[DATABASE_BACKEND.md](DATABASE_BACKEND.md)). A maioría dos aloxamentos que fan andar un WordPress
xa soportan PHP e MariaDB, así que ese despregamento é o que paga a pena probar primeiro. Esta
compilación estática é para o caso máis restrinxido: aloxamentos que só poden servir ficheiros
simples, sen PHP de ningún tipo. Reutiliza a mesma idea que xa usan a demo en liña e a app móbil,
só que sen os seus extras.

## O que conservas / o que perdes

- **Conservas:** o rastrexador enteiro — plan semanal, adestramentos guiados, regras de
  progresión, 1RM, estatísticas, mapa muscular, mapa de calor de actividade, importadores
  (FitNotes/Strong/Hevy/Apple Health), copia de seguridade e restauración en JSON. Totalmente
  usable, só que local a un navegador.
- **Perdes:** iniciar sesión, sincronización entre dispositivos, o panel de administración e as
  notificacións push — as catro cousas precisan do backend `api/`, que un aloxamento estático non
  pode executar. Os datos viven só no almacenamento dese navegador (igual que navegar sen conta
  na compilación normal), así que exporta unha copia de seguridade desde Axustes de vez en cando,
  e sempre despois de limpar os datos do sitio ou de cambiar de navegador/dispositivo.

## 1. Compílao

Isto precisa Node.js unha soa vez, nun computador calquera — non no propio aloxamento, e nunca
máis despois desta compilación.

```bash
cd frontend
npm ci
npm run build:static
```

Iso xera `frontend/dist/` — a app enteira como HTML/CSS/JS/imaxes simples. O *contido* dese
cartafol é todo o que tes que subir; nada máis neste repositorio precisa chegar ao servidor.

Non tes Node.js instalado e non queres instalalo? Calquera opción gratuíta e sen instalación
tamén serve — abre o repositorio en
[StackBlitz](https://stackblitz.com/github/IPardelo/oXimnasioDoPardelo) ou un GitHub Codespace e
executa os mesmos dous comandos na súa terminal, ou pídelle a alguén con Node que os execute e che
pase o cartafol `dist/`.

## 2. Súbeo

Copia o *contido* de `frontend/dist/` (non o cartafol en si) a onde o teu aloxamento sirva
ficheiros — `public_html`, o cartafol dun subdominio, ou un subcartafol se queres a app en
`oteusitio.com/gym/`. Unha simple subida por FTP ou polo xestor de ficheiros abonda — non hai nada
que configurar no servidor:

- A app usa enrutamento por hash (`oteusitio.com/#/home`), así que calquera URL resolve sempre ao
  mesmo `index.html` — non fai falta `.htaccess` nin regras de reescritura, a diferenza dun
  despregamento típico dunha SPA.
- As rutas da compilación son relativas (`base: './'` en `vite.config.js`), así que o mesmo
  `dist/` funciona tanto na raíz do teu dominio coma nun subcartafol — tampouco hai que configurar
  rutas.
- HTTPS é o único requisito real: a maioría dos aloxamentos emiten un certificado gratuíto
  automaticamente, e é o que fai que a app se poida instalar como PWA na pantalla de inicio con
  funcionamento sen conexión mediante o seu service worker.

## 3. Actualizacións posteriores

Descarga os últimos cambios, repite o paso 1, e volve subir `frontend/dist/` por riba da copia
anterior. Quen xa a visitase conserva o que teña no almacenamento do seu navegador — só cambia o
"esqueleto" da app.

## Cambiaches de opinión sobre autoaloxalo de verdade?

Nada aquí é un camiño sen volta: esta compilación estática é o mesmo código, só que sen o
backend. Pasar nun futuro a un aloxamento que soporte PHP e MariaDB — o que inclúe a maioría dos
plans que poden executar WordPress — dáche a configuración completa en
[DATABASE_BACKEND.md](DATABASE_BACKEND.md): inicio de sesión con Google/usuario, sincronización,
panel de administración e notificacións push, sen perder nada do que xa levas rexistrado (a
exportación/importación JSON de cada usuario funciona igual de ben nun sentido que noutro).
