<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * URL canonique déclarée par chaque page (#227).
 *
 * Sans `rel=canonical`, tout paramètre inconnu — campagne `utm_`, lien entrant approximatif, tri
 * mémorisé — fabrique une page de plus aux yeux des moteurs, avec le même contenu. Le `robots.txt`
 * ferme les paramètres connus, mais il ne peut rien contre ceux qu'il ne nomme pas.
 *
 * L'inverse coûte plus cher encore : les paramètres qui portent un contenu réellement distinct
 * doivent survivre à la canonisation, sous peine de fermer les chemins de découverte que le
 * `robots.txt` laisse ouverts exprès.
 *
 * - `index.php?courant=` : l'accueil n'affiche qu'un seul jour, les événements à venir ne sont
 *   atteignables que par lui ;
 * - `?page=` des listes de lieux et d'organisateurs, `?periode=` et `?page=` des fiches lieu et
 *   organisateur : pagination réelle, dont Google demande qu'elle se canonise sur elle-même et non
 *   sur la page 1.
 *
 * Les assertions portent sur la fin de l'URL et jamais sur sa totalité : l'hôte canonique dépend de
 * `SITE_CANONICAL_URL`, propre à l'instance testée.
 */
class CanonicalCest
{
    private const CANONICAL = 'link[rel=canonical]';

    private function grabCanonical(SiteTester $I): string
    {
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeNumberOfElements(self::CANONICAL, 1);

        return $I->grabAttributeFrom(self::CANONICAL, 'href');
    }

    /**
     * Un jour assez loin pour n'être jamais celui du jour, y compris entre minuit et 6 h où
     * l'agenda considère encore la veille comme la journée courante.
     */
    private function jourFutur(): DateTimeImmutable
    {
        return new DateTimeImmutable('first day of +2 months');
    }

    public function accueilSeCanoniseSurLaRacine(SiteTester $I)
    {
        $I->amOnPage('/');

        $canonical = $this->grabCanonical($I);

        $I->assertStringEndsWith('/', $canonical, "l'accueil doit se canoniser sur la racine du site");
        $I->assertStringNotContainsString('?', $canonical);
    }

    /**
     * Les trois formes de bruit d'un même contenu : la campagne, le tri mémorisé en session, et
     * `index.php` là où la racine suffit.
     */
    public function accueilIgnoreLesParametresQuiNeChangentRien(SiteTester $I)
    {
        $I->amOnPage('/index.php?utm_source=newsletter&tri_agenda=horaire_debut');

        $canonical = $this->grabCanonical($I);

        $I->assertStringEndsWith('/', $canonical);
        $I->assertStringNotContainsString('utm_source', $canonical);
        $I->assertStringNotContainsString('tri_agenda', $canonical);
    }

    /**
     * L'hôte de la canonique ne doit pas suivre celui de la requête, sans quoi chaque doublon
     * d'hôte ou de schéma se déclare canonique et la duplication que la balise existe pour réduire
     * reste entière. La preuve est nette quand l'URL de test diffère de l'hôte canonique déclaré.
     */
    public function laCanoniqueNeSuitPasLHoteDeLaRequete(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_CANONICAL_URL');

        $I->amOnPage('/lieu/lieux.php');

        $I->assertStringStartsWith(
            TestEnv::get('LADECADANSE_SITE_CANONICAL_URL') . '/',
            $this->grabCanonical($I),
            "la canonique a suivi l'hôte de la requête au lieu de SITE_CANONICAL_URL"
        );
    }

    public function agendaDuJourGardeSaDate(SiteTester $I)
    {
        $jour = $this->jourFutur();
        $I->amOnPage('/index.php?courant=' . $jour->format('Y-m-d') . '&utm_medium=facebook');

        $canonical = $this->grabCanonical($I);

        $I->assertStringEndsWith('/index.php?courant=' . $jour->format('Y-m-d'), $canonical);
    }

    /**
     * La date est acceptée sans zéro de tête (`[0-9]{1,2}` dans index.php) : deux écritures du même
     * jour, donc deux URL, que la canonique doit réduire à une seule.
     */
    public function agendaDuJourNormaliseLaDate(SiteTester $I)
    {
        $jour = $this->jourFutur();
        $I->assertSame('1', $jour->format('j'), 'le jour de référence doit s\'écrire sans zéro de tête');

        $I->amOnPage('/index.php?courant=' . $jour->format('Y-n-j'));

        $canonical = $this->grabCanonical($I);

        $I->assertStringEndsWith('/index.php?courant=' . $jour->format('Y-m-d'), $canonical);
    }

    /**
     * La regex de validation laisse passer des dates qui n'existent pas. Normalisées telles quelles,
     * elles feraient servir et canoniser un autre jour que celui demandé (`DateTime` décale le
     * 30 février au 2 mars) quand elles ne feraient pas tomber la page (il lève sur un 13e mois).
     */
    public function agendaRefuseUneDateImpossible(SiteTester $I)
    {
        foreach (['2030-2-30', '2030-13-45'] as $impossible)
        {
            $I->amOnPage('/index.php?courant=' . $impossible);

            $canonical = $this->grabCanonical($I);

            $I->assertStringEndsWith('/', $canonical, "$impossible aurait dû retomber sur la journée courante");
            $I->assertStringNotContainsString('courant', $canonical);
        }
    }

    public function ficheEvenementGardeSonIdentifiant(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_TEST_EVENT_ID_AUTEUR');

        $idE = TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR');
        $I->amOnPage('/event/evenement.php?idE=' . $idE . '&utm_source=twitter');

        $canonical = $this->grabCanonical($I);

        $I->assertStringEndsWith('/event/evenement.php?idE=' . $idE, $canonical);
    }

    /**
     * Les fiches paginent aussi les événements passés du lieu. Le contenu n'entre pas dans
     * l'assertion, qui ne porte que sur la forme de l'URL : n'importe quel lieu existant convient.
     */
    public function ficheLieuGardeSaPeriodeEtSaPage(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_TEST_LIEU_ID');

        $idL = TestEnv::getInt('LADECADANSE_TEST_LIEU_ID');
        $I->amOnPage('/lieu/lieu.php?idL=' . $idL . '&periode=ancien&page=3');

        $canonical = $this->grabCanonical($I);

        $I->assertStringEndsWith('/lieu/lieu.php?idL=' . $idL . '&periode=ancien&page=3', html_entity_decode($canonical));
    }

    public function listeDeLieuxSansPaginationSeCanoniseSurElleMeme(SiteTester $I)
    {
        $I->amOnPage('/lieu/lieux.php?order=nom');

        $canonical = $this->grabCanonical($I);

        $I->assertStringEndsWith('/lieu/lieux.php', $canonical);
    }

    public function listeDeLieuxPaginee(SiteTester $I)
    {
        $I->amOnPage('/lieu/lieux.php?page=2&order=nom');

        $canonical = $this->grabCanonical($I);

        $I->assertStringEndsWith('/lieu/lieux.php?page=2', $canonical);
    }

    public function listeDOrganisateursPaginee(SiteTester $I)
    {
        $I->amOnPage('/organisateur/organisateurs.php?page=3');

        $canonical = $this->grabCanonical($I);

        $I->assertStringEndsWith('/organisateur/organisateurs.php?page=3', $canonical);
    }

    /**
     * Le PATH_INFO sert la même page sous une infinité de chemins : la canonique ramène chacun
     * d'eux sur le script réellement exécuté. Le handler PHP de l'instance testée peut refuser ce
     * chemin, auquel cas le défaut ne peut pas s'y produire et il n'y a rien à vérifier.
     */
    public function pathInfoNeFabriquePasDeNouvellePage(SiteTester $I)
    {
        $I->amOnPage('/articles/apropos.php/xyz');

        if ($I->grabResponseCode() !== HttpCode::OK)
        {
            $I->markTestSkipped("le handler PHP de l'instance testée refuse le PATH_INFO");
        }

        $I->assertStringEndsWith('/articles/apropos.php', $this->grabCanonical($I));
    }

    /**
     * La page d'erreur répond sous l'adresse demandée, quelle qu'elle soit. Une canonique la
     * ferait désigner `/misc/error.php`, elle-même indexable, depuis chaque 404 du site.
     *
     * Elle est demandée directement plutôt que par une adresse inexistante : le `ErrorDocument`
     * qui l'y route vit dans le `.htaccess`, un fichier composé par `composer config:build` et
     * absent de bien des instances de développement, où le serveur rendrait alors sa propre page
     * d'erreur — sans canonique elle non plus, et le test passerait sans rien prouver.
     */
    public function pageDErreurNeDeclarePasDeCanonique(SiteTester $I)
    {
        $I->amOnPage('/misc/error.php');

        $I->dontSeeElement(self::CANONICAL);
        $I->dontSeeElement('meta[property="og:url"]');
    }
}
