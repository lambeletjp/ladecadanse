<?php

declare(strict_types=1);

namespace Ladecadanse\Utils;

/**
 * Validation des paramètres d'URL (query string).
 *
 * Extrait de Validateur (issue #153) : ces méthodes valident les valeurs de
 * $_GET avant utilisation. Séparées du validateur de formulaire à état
 * (Validateur) pour lever l'incohérence statique/instance : ici tout est
 * statique et sans effet de bord, l'invalidité étant signalée par exception.
 */
final class QueryParamValidator
{
    /**
     * Variante non bloquante de validateUrlQueryValue().
     *
     * Pour les paramètres d'url facultatifs, dont l'appelant garde simplement
     * sa valeur par défaut : une vieille url ou un passage de bot ne doit pas
     * finir en exception non rattrapée.
     */
    public static function isAcceptedUrlQueryValue(mixed $get, string $type, mixed $tab = ''): bool
    {
        try
        {
            self::validateUrlQueryValue($get, $type, 1, $tab);
        }
        catch (\Exception)
        {
            return false;
        }

        return true;
    }

    /**
     * Numéro de page de pagination issu de la query string.
     *
     * Jamais bloquant : les bots suivent régulièrement des urls abîmées
     * (« ?page=3&region=vd » recopié en « ?page=3®ion=vd »), ce qui finissait
     * en fatale. Toute valeur non entière ou inférieure à 1 retombe sur $defaut.
     */
    public static function pageFromQuery(mixed $get, int $defaut = 1): int
    {
        // is_scalar() écarte "?page[]=3", qui sinon déclencherait une conversion
        // de tableau en chaîne dans validateUrlQueryValue()
        if (!is_scalar($get) || !self::isAcceptedUrlQueryValue($get, "int"))
        {
            return $defaut;
        }

        return max(1, (int) $get);
    }

    /**
     * Jour ISO issu de la query string, normalisé en AAAA-MM-JJ.
     *
     * Le mois et le jour sont acceptés sans zéro de tête : réunir les deux
     * écritures évite deux URL pour la même journée. Jamais bloquant, comme
     * pageFromQuery() : une date que le calendrier ne connaît pas (30 février)
     * retombe sur $defaut au lieu d'être décalée par DateTime, et un 13e mois
     * au lieu d'y lever une fatale.
     */
    public static function jourFromQuery(mixed $get, string $defaut): string
    {
        if (!is_scalar($get) || !preg_match("/^([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})$/", trim((string) $get), $jour))
        {
            return $defaut;
        }

        if (!checkdate((int) $jour[2], (int) $jour[3], (int) $jour[1]))
        {
            return $defaut;
        }

        return sprintf("%04d-%02d-%02d", $jour[1], $jour[2], $jour[3]);
    }

    /**
     * Valide une valeur de query string selon le type attendu.
     *
     * @param mixed  $get    Valeur brute issue de $_GET
     * @param string $type   int|string|date|enum|alpha_numeric
     * @param mixed  $statut 1 = obligatoire (lève une exception si vide) ;
     *                       0 = facultatif (retourne null si vide) ;
     *                       autre valeur = valeur par défaut retournée si vide
     * @param mixed  $tab    Liste des valeurs acceptées pour le type enum
     * @return mixed La valeur validée, la valeur par défaut, ou null
     * @throws \Exception si la valeur est absente alors qu'obligatoire, ou invalide
     */
    public static function validateUrlQueryValue(mixed $get, string $type, mixed $statut, mixed $tab = ''): mixed
    {
        $erreur = "";

        if ($get == '')
        {
            if ($statut == 1)
            {
                $erreur = "Ce paramètre est obligatoire";
            }
            else if ($statut != 0)
            {
                return $statut;
            }
            else
            {
                return null;
            }
        }
        else
        {
            $get = trim((string) $get);

            if ($type == "int")
            {
                if (is_numeric($get))
                {
                    return $get;
                }
                else
                {
                    $erreur = $get." n'est pas un numeric";
                }
            }
            else if ($type == "string")
            {
                // après le cast/trim ci-dessus, $get est toujours une chaine
                return $get;
            }
            else if ($type == "date")
            {
                if (preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $get))
                {
                    return $get;
                }
                else
                {
                    $erreur = $get." n'est pas une date";
                }
            }
            else if ($type == "enum")
            {
                if (is_array($tab) && in_array($get, $tab))
                {
                    return $get;
                }
                else
                {
                    $erreur = $get." n'est pas une valeur acceptée";
                }
            }
            else if ($type == "alpha_numeric")
            {
                if (preg_match("/^\w+$/i", $get))
                {
                    return $get;
                }
                else
                {
                    $erreur = $get." n'est pas un alpha_numeric";
                }
            }
        }

        throw new \Exception($erreur);
    }
}
