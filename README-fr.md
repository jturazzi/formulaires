# Formulaires

> 🇬🇧 [English version](README.md)

**Formulaires** est un créateur de formulaires open source et auto-hébergé. Créez des formulaires avec sections, questions et envoi de fichiers, partagez un lien public, et centralisez toutes les réponses - avec une conformité RGPD intégrée.

Construit avec **Laravel 13**, **Inertia.js v3** et **Vue 3** (TypeScript, Tailwind CSS v4).

## Fonctionnalités

- **Builder de formulaires** - sections, réorganisation par glisser-déposer, dix types de questions : texte court/long, email, nombre, date, choix unique, choix multiples, liste déroulante, fichier et blocs de texte libres.
- **Personnalisation** - logo et couleur de thème par formulaire.
- **Lien public** - les répondants n'ont pas besoin de compte. Options par formulaire : vérification d'email par code à 6 chiffres, limite de réponses, date de clôture.
- **Réponses centralisées** - vue tableau, vue détail, téléchargement sécurisé des fichiers, export CSV (compatible Excel).
- **SSO Microsoft 365** - les gestionnaires se connectent avec leur compte d'organisation (Azure AD / Entra ID). Le premier utilisateur connecté devient administrateur. L'authentification classique email/mot de passe reste disponible en option pour les déploiements sans Microsoft 365.
- **Rôles** - administrateurs (gestion des utilisateurs, pages légales, réglages RGPD et diagnostics système) et créateurs (gestion de leurs propres formulaires).
- **RGPD** - case de consentement explicite avec liens vers des CGU et une politique de confidentialité éditables, durée de conservation des documents par formulaire, purge automatique quotidienne des réponses expirées **et de leurs fichiers**, suppression individuelle des réponses (droit à l'effacement).
- **Bilingue** - français et anglais inclus (prêt pour d'autres langues).
- **Notifications** - email optionnel au propriétaire du formulaire à chaque nouvelle réponse.

## Prérequis

- PHP ≥ 8.3 avec les extensions usuelles
- Composer
- Node.js ≥ 20 et npm
- MySQL/MariaDB (recommandé), PostgreSQL ou SQLite

## Installation

```bash
git clone https://github.com/jturazzi/formulaires.git
cd formulaires

composer install
npm install

cp .env.example .env
php artisan key:generate

# Configurez les variables DB_* dans .env, puis :
php artisan migrate --seed   # insère les modèles de pages légales RGPD
php artisan storage:link

npm run build
```

Pour le développement local :

```bash
composer run dev   # sert l'app, la file d'attente, les logs et Vite
```

### Purge planifiée (RGPD)

Ajoutez le scheduler Laravel à votre crontab pour que les réponses expirées soient purgées chaque jour :

```cron
* * * * * cd /chemin-du-projet && php artisan schedule:run >> /dev/null 2>&1
```

Vous pouvez prévisualiser ce qui serait supprimé avec :

```bash
php artisan responses:purge --dry-run
```

### Configuration du SSO Microsoft 365

1. Rendez-vous sur le [centre d'administration Microsoft Entra](https://entra.microsoft.com) → *Inscriptions d'applications* → *Nouvelle inscription*.
2. Définissez l'URI de redirection (type *Web*) : `https://votre-domaine/auth/microsoft/callback`.
3. Créez un secret client dans *Certificats et secrets*.
4. Renseignez `.env` :

```dotenv
MICROSOFT_CLIENT_ID=id-de-votre-application
MICROSOFT_CLIENT_SECRET=votre-secret-client
MICROSOFT_TENANT_ID=id-de-votre-tenant   # ou "common" pour autoriser tout tenant
```

Le **premier utilisateur** qui se connecte devient administrateur. Pas de Microsoft 365 ? Mettez `REGISTRATION_ENABLED=true` pour autoriser l'inscription par email/mot de passe.

### Emails

Les codes de vérification des répondants et les notifications sont envoyés par email. Configurez les variables `MAIL_*` dans `.env` (tout fournisseur SMTP convient).

### Suivi des erreurs (optionnel)

L'application intègre [Sentry](https://sentry.io), désactivé tant qu'il n'est pas configuré. Renseignez `SENTRY_LARAVEL_DSN` (backend) et `VITE_SENTRY_DSN` (frontend, recompilez les assets après l'avoir renseigné) dans `.env` pour l'activer. Laissez les deux vides pour le garder désactivé.

## Notes RGPD

- La case de consentement est **toujours obligatoire** avant l'envoi d'une réponse ; l'horodatage du consentement est enregistré avec la réponse.
- Les pages CGU et politique de confidentialité sont éditables depuis le panneau d'administration (Markdown, français et anglais). Des modèles par défaut sont fournis - **remplacez les `[zones entre crochets]` par les informations de votre organisation**.
- Chaque formulaire affiche sa durée de conservation aux répondants. Passé ce délai, les réponses et les documents transmis sont supprimés automatiquement par la purge quotidienne.
- La suppression d'une réponse, d'un formulaire ou d'un utilisateur supprime aussi tous les fichiers associés.
- Les fichiers transmis sont stockés sur le disque local privé et servis uniquement au propriétaire du formulaire et aux administrateurs, jamais publiquement.
- Les DevTools intégrés d'Inertia (enregistreur de requêtes/réponses, actif par défaut hors `production`) sont **désactivés** par défaut dans `.env.example` (`INERTIA_DEVTOOLS_ENABLED=false`) : sans quoi les soumissions de formulaires publics seraient enregistrées sur disque en dehors du système de conservation/purge de l'application. Ne le réactivez que pour du débogage local.

## Tests

```bash
php artisan test        # suite de tests backend
composer analyse        # analyse statique (Larastan)
npm run lint             # lint frontend
```

## Contribuer

Les issues et pull requests sont les bienvenues ! Merci de lancer `php artisan test`, `composer analyse`, `npm run lint` et `npm run format` avant de soumettre.

## Licence

[MIT](LICENSE)
