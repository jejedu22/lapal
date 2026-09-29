<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Formatage homogène des montants et des poids dans les templates.
 */
class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // 4.5 => "4,50 €"
            new TwigFilter('euros', [self::class, 'euros']),
            // 0.75 => "0,75 kg" ; 29.5 => "29,5 kg" ; 1 => "1 kg"
            new TwigFilter('kg', [self::class, 'kg']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('couleur_hex', [self::class, 'couleurHex']),
        ];
    }

    /**
     * Couleurs AdminLTE / Bootstrap proposées dans la configuration.
     */
    public static function couleurHex(?string $couleur): string
    {
        $couleurs = [
            'blue' => '#007bff', 'cyan' => '#17a2b8', 'gray' => '#6c757d', 'gray-dark' => '#343a40',
            'indigo' => '#6610f2', 'yellow' => '#ffc107', 'orange' => '#fd7e14', 'pink' => '#e83e8c',
            'red' => '#dc3545', 'teal' => '#20c997', 'green' => '#28a745', 'purple' => '#6f42c1',
        ];

        return $couleurs[$couleur] ?? $couleurs['orange'];
    }

    public static function euros($montant): string
    {
        return number_format((float) $montant, 2, ',', "\u{202F}") . "\u{00A0}€";
    }

    public static function kg($poids): string
    {
        $texte = number_format((float) $poids, 2, ',', "\u{202F}");
        $texte = rtrim(rtrim($texte, '0'), ',');

        return ('' === $texte || '-0' === $texte ? '0' : $texte) . "\u{00A0}kg";
    }
}
