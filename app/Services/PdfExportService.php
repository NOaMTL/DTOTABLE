<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class PdfExportService
{
    public function generatePdf(Collection $data, array $filters = []): string
    {
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 9,
            'margin_footer' => 9,
            'fontDir' => array_merge($fontDirs, []),
            'fontdata' => $fontData,
        ]);

        $html = $this->generateHtml($data, $filters);
        $mpdf->WriteHTML($html);

        $filename = $this->generateFilename();
        $filepath = storage_path('app/public/exports/' . $filename);

        // Créer le répertoire si nécessaire
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $mpdf->Output($filepath, 'F');

        return $filename;
    }

    private function generateHtml(Collection $data, array $filters): string
    {
        $filtersSummary = $this->buildFiltersSummary($filters);
        $total = $data->sum('montant');
        $count = $data->count();

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; font-size: 9pt; }
                h1 { font-size: 16pt; text-align: center; margin-bottom: 20px; }
                .header { margin-bottom: 15px; }
                .filters { background: #f5f5f5; padding: 10px; margin-bottom: 15px; font-size: 8pt; }
                table { width: 100%; border-collapse: collapse; font-size: 8pt; }
                th { background-color: #4CAF50; color: white; padding: 8px; text-align: left; font-weight: bold; }
                td { padding: 6px; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .footer { margin-top: 20px; font-size: 8pt; text-align: right; }
                .summary { background: #e8f5e9; padding: 10px; margin-top: 15px; font-weight: bold; }
            </style>
        </head>
        <body>
            <h1>Export des Données Bancaires</h1>
            
            <div class="header">
                <strong>Date d\'export:</strong> ' . now()->format('d/m/Y H:i:s') . '<br>
                <strong>Nombre d\'enregistrements:</strong> ' . $count . '
            </div>
            
            ' . ($filtersSummary ? '<div class="filters"><strong>Filtres appliqués:</strong><br>' . $filtersSummary . '</div>' : '') . '
            
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Date</th>
                        <th>Libellé</th>
                        <th class="text-right">Montant</th>
                        <th>Devise</th>
                        <th>Compte</th>
                        <th>Agence</th>
                        <th>Type</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($data as $row) {
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($row->reference) . '</td>
                        <td class="text-center">' . $row->date_operation->format('d/m/Y') . '</td>
                        <td>' . htmlspecialchars($row->libelle) . '</td>
                        <td class="text-right">' . number_format($row->montant, 2, ',', ' ') . '</td>
                        <td>' . htmlspecialchars($row->devise) . '</td>
                        <td>' . htmlspecialchars($row->compte) . '</td>
                        <td>' . htmlspecialchars($row->agence ?? '') . '</td>
                        <td>' . htmlspecialchars($row->type_operation ?? '') . '</td>
                        <td>' . htmlspecialchars($row->statut ?? '') . '</td>
                    </tr>';
        }

        $html .= '
                </tbody>
            </table>
            
            <div class="summary">
                <strong>Total:</strong> ' . number_format($total, 2, ',', ' ') . ' | 
                <strong>Nombre de lignes:</strong> ' . $count . '
            </div>
            
            <div class="footer">
                Généré le ' . now()->format('d/m/Y à H:i:s') . '
            </div>
        </body>
        </html>';

        return $html;
    }

    private function buildFiltersSummary(array $filters): string
    {
        $summary = [];

        if (!empty($filters['dateDebut'])) {
            $summary[] = 'Date début: ' . $filters['dateDebut'];
        }

        if (!empty($filters['dateFin'])) {
            $summary[] = 'Date fin: ' . $filters['dateFin'];
        }

        if (!empty($filters['compte'])) {
            $summary[] = 'Compte: ' . $filters['compte'];
        }

        if (!empty($filters['montantMin'])) {
            $summary[] = 'Montant min: ' . number_format($filters['montantMin'], 2);
        }

        if (!empty($filters['montantMax'])) {
            $summary[] = 'Montant max: ' . number_format($filters['montantMax'], 2);
        }

        if (!empty($filters['devise'])) {
            $summary[] = 'Devise: ' . $filters['devise'];
        }

        if (!empty($filters['typeOperation'])) {
            $summary[] = 'Type: ' . $filters['typeOperation'];
        }

        if (!empty($filters['statut'])) {
            $summary[] = 'Statut: ' . $filters['statut'];
        }

        return implode(' | ', $summary);
    }

    private function generateFilename(): string
    {
        return 'export_tableau_' . now()->format('Y-m-d_His') . '.pdf';
    }
}
