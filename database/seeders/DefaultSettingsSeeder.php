<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DefaultSettingsSeeder extends Seeder
{
    /**
     * Seed GDPR-ready default legal content. Administrators are expected to
     * adapt the placeholders ([...]) from the admin panel.
     */
    public function run(): void
    {
        $defaults = [
            'default_retention_days' => (string) config('formulaires.default_retention_days'),
            'terms_fr' => $this->termsFr(),
            'terms_en' => $this->termsEn(),
            'privacy_fr' => $this->privacyFr(),
            'privacy_en' => $this->privacyEn(),
        ];

        foreach ($defaults as $key => $value) {
            if (Setting::get($key) === null) {
                Setting::set($key, $value);
            }
        }
    }

    private function termsFr(): string
    {
        return <<<'MD'
# Conditions générales d'utilisation

## 1. Objet

La présente plateforme permet à [Votre organisation] de créer des formulaires en ligne et de collecter les réponses de personnes extérieures.

## 2. Acceptation

En soumettant une réponse à un formulaire, vous acceptez les présentes conditions générales d'utilisation ainsi que la politique de confidentialité.

## 3. Utilisation du service

Vous vous engagez à fournir des informations exactes et à ne pas transmettre de contenu illicite, diffamatoire ou portant atteinte aux droits de tiers. Les fichiers transmis ne doivent pas contenir de programme malveillant.

## 4. Données personnelles et conservation des documents

Les réponses et les documents transmis via un formulaire sont conservés pour la durée indiquée sur ce formulaire, puis supprimés automatiquement. Pour en savoir plus sur le traitement de vos données et vos droits (accès, rectification, effacement…), consultez la [politique de confidentialité](/privacy).

## 5. Responsabilité

[Votre organisation] met en œuvre les moyens raisonnables pour assurer la disponibilité et la sécurité de la plateforme, sans garantie d'absence d'interruption.

## 6. Contact

Pour toute question relative aux présentes conditions : [adresse email de contact].

_Dernière mise à jour : [date]._
MD;
    }

    private function termsEn(): string
    {
        return <<<'MD'
# Terms of use

## 1. Purpose

This platform allows [Your organization] to create online forms and collect responses from external users.

## 2. Acceptance

By submitting a response to a form, you accept these terms of use and the privacy policy.

## 3. Use of the service

You agree to provide accurate information and not to submit unlawful or defamatory content, or content infringing third-party rights. Uploaded files must not contain malicious software.

## 4. Personal data and document retention

Responses and documents submitted through a form are kept for the retention period stated on that form, then automatically deleted. To learn more about how your data is processed and about your rights (access, rectification, erasure…), see the [privacy policy](/privacy).

## 5. Liability

[Your organization] takes reasonable measures to ensure the availability and security of the platform, without guaranteeing uninterrupted service.

## 6. Contact

For any question about these terms: [contact email address].

_Last updated: [date]._
MD;
    }

    private function privacyFr(): string
    {
        return <<<'MD'
# Politique de confidentialité

## 1. Responsable du traitement

Le responsable du traitement est [Votre organisation], [adresse], joignable à [adresse email de contact].

## 2. Données collectées

Lorsque vous répondez à un formulaire, nous collectons uniquement les informations que vous saisissez : vos réponses aux questions, les fichiers que vous transmettez et, si le formulaire l'exige, votre adresse email (utilisée pour vérifier votre identité).

Aucun cookie de suivi ni outil de mesure d'audience tiers n'est utilisé sur les pages publiques de formulaire.

## 3. Finalité et base légale

Les données sont collectées dans le but indiqué par le formulaire auquel vous répondez. La base légale du traitement est votre consentement, matérialisé par la case à cocher avant l'envoi de votre réponse.

## 4. Durée de conservation

Chaque formulaire précise sa durée de conservation. À l'issue de cette durée, les réponses **et l'ensemble des documents transmis** sont supprimés automatiquement et définitivement de nos serveurs.

## 5. Destinataires

Vos réponses sont accessibles uniquement au créateur du formulaire et aux administrateurs de la plateforme. Elles ne sont ni vendues ni transmises à des tiers.

## 6. Vos droits

Conformément au Règlement général sur la protection des données (RGPD), vous disposez d'un droit d'accès, de rectification, d'effacement, de limitation et d'opposition sur vos données. Vous pouvez exercer ces droits en écrivant à [adresse email de contact]. Vous pouvez également introduire une réclamation auprès de la CNIL (www.cnil.fr).

## 7. Sécurité

Les documents transmis sont stockés sur des serveurs sécurisés et ne sont pas accessibles publiquement. Les accès des gestionnaires s'effectuent via une authentification sécurisée.

_Dernière mise à jour : [date]._
MD;
    }

    private function privacyEn(): string
    {
        return <<<'MD'
# Privacy policy

## 1. Data controller

The data controller is [Your organization], [address], reachable at [contact email address].

## 2. Data we collect

When you answer a form, we only collect the information you provide: your answers, the files you upload and, when the form requires it, your email address (used to verify your identity).

No tracking cookies or third-party analytics are used on public form pages.

## 3. Purpose and legal basis

Data is collected for the purpose stated by the form you answer. The legal basis for processing is your consent, given through the checkbox before submitting your response.

## 4. Retention period

Each form states its retention period. Once this period has elapsed, responses **and all uploaded documents** are automatically and permanently deleted from our servers.

## 5. Recipients

Your responses are only accessible to the form's creator and to the platform administrators. They are never sold or shared with third parties.

## 6. Your rights

Under the General Data Protection Regulation (GDPR), you have the right to access, rectify, erase, restrict and object to the processing of your data. You can exercise these rights by writing to [contact email address]. You may also lodge a complaint with your supervisory authority.

## 7. Security

Uploaded documents are stored on secured servers and are not publicly accessible. Manager access requires secure authentication.

_Last updated: [date]._
MD;
    }
}
