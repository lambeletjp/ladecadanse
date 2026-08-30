<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\HtmlShrink;

/**
 * Assemblage de l'URL propre servant og:url et rel=canonical (#227).
 *
 * Cinq pages la construisent, chacune avec ses propres paramètres porteurs de contenu : le point
 * unique est ici, pour que personne n'ait à se souvenir du « ? » de tête ni du « & » des suivants.
 */
final class UrlCanoniqueTest extends Unit
{
    public function testScriptSeulQuandAucunParametre(): void
    {
        $this->assertSame('lieu/lieux.php', HtmlShrink::urlCanonique('lieu/lieux.php'));
    }

    public function testParametreNulOmis(): void
    {
        $this->assertSame('lieu/lieux.php', HtmlShrink::urlCanonique('lieu/lieux.php', ['page' => null]));
    }

    public function testPremierParametreIntroduitParUnPointDInterrogation(): void
    {
        $this->assertSame('index.php?courant=2026-11-01', HtmlShrink::urlCanonique('index.php', ['courant' => '2026-11-01']));
    }

    public function testParametresSuivantsIntroduitsParUneEsperluette(): void
    {
        $this->assertSame(
            'lieu/lieu.php?idL=42&periode=ancien&page=3',
            HtmlShrink::urlCanonique('lieu/lieu.php', ['idL' => 42, 'periode' => 'ancien', 'page' => 3])
        );
    }

    /**
     * Un paramètre nul au milieu ne doit pas laisser d'esperluette orpheline.
     */
    public function testTrouAuMilieuReferme(): void
    {
        $this->assertSame(
            'lieu/lieu.php?idL=42&page=3',
            HtmlShrink::urlCanonique('lieu/lieu.php', ['idL' => 42, 'periode' => null, 'page' => 3])
        );
    }

    public function testValeurEncodee(): void
    {
        $this->assertSame('lieu/lieu.php?periode=a%20b', HtmlShrink::urlCanonique('lieu/lieu.php', ['periode' => 'a b']));
    }

    /**
     * Zéro et chaîne vide sont des valeurs, pas des absences : seul null retire le paramètre.
     */
    public function testSeulNullRetireLeParametre(): void
    {
        $this->assertSame('page.php?a=0&b=', HtmlShrink::urlCanonique('page.php', ['a' => 0, 'b' => '']));
    }
}
