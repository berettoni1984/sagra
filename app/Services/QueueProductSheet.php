<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Queue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageMargin;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foglio Excel A4 col listino di una coda — nome prodotto e prezzo stampati, la
 * colonna della quantità vuota da riempire a mano: serve a chi prende le
 * ordinazioni o conta il venduto in cassa e in cucina.
 *
 * L'accoppiamento alto è tutto verso i value object di openspout (Style,
 * Border, PageSetup, ...), che sono il modo in cui la libreria si configura.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class QueueProductSheet
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** Larghezze in caratteri: il nome prodotto è lungo, prezzo e qty no. */
    private const LARGHEZZA_NOME = 55.0;

    private const LARGHEZZA_PREZZO = 16.0;

    private const LARGHEZZA_QTY = 14.0;

    /** Excel rifiuta nomi foglio oltre 31 caratteri o con []:*?/\ */
    private const MAX_NOME_FOGLIO = 31;

    /**
     * Livewire consegna il download catturando l'output della risposta: lo
     * streaming su php://output va bene perché openspout scrive lo zip in
     * sequenza, senza tornare indietro nel file.
     */
    public function download(Queue $queue): StreamedResponse
    {
        return response()->streamDownload(
            fn () => $this->write($queue, 'php://output'),
            $this->fileName($queue),
            ['Content-Type' => self::MIME],
        );
    }

    public function fileName(Queue $queue): string
    {
        $slug = Str::slug($queue->label) ?: 'fila-'.$queue->getKey();

        return 'listino-'.$slug.'-'.now()->format('Y-m-d').'.xlsx';
    }

    public function write(Queue $queue, string $path): void
    {
        $writer = new Writer($this->options());
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($this->sheetName($queue));

        $writer->addRow(Row::fromValues(
            [__('filament.Product Name'), __('filament.Price'), __('filament.Qty')],
            $this->headerStyle(),
        ));

        $bordo = $this->bodyStyle();
        $prezzo = $this->priceStyle();
        // La colonna della quantità resta vuota, da riempire a mano: uno stile
        // con bordi è l'unico modo per cui openspout scrive comunque la cella
        // vuota invece di ometterla, lasciando la tabella spezzata.
        $qty = $this->qtyStyle();

        foreach ($this->products($queue) as $product) {
            $writer->addRow(new Row([
                Cell::fromValue($product->name, $bordo),
                Cell::fromValue((float) $product->price, $prezzo),
                Cell::fromValue(null, $qty),
            ]));
        }

        $writer->close();
    }

    /**
     * I prodotti della coda in ordine di listino, esclusi i disabilitati: sono
     * quelli che la cassa di quella fila mostra davvero.
     *
     * @return Collection<int, Product>
     */
    public function products(Queue $queue): Collection
    {
        return $queue->products()
            ->whereIsDisabled(false)
            ->orderBy('products.order')
            ->orderBy('products.id')
            ->get();
    }

    private function options(): Options
    {
        $options = new Options;
        // A4 verticale, ridotto a una pagina in larghezza: senza fitToWidth la
        // colonna del prezzo finisce stampata su un secondo foglio.
        $options->setPageSetup(new PageSetup(
            pageOrientation: PageOrientation::PORTRAIT,
            paperSize: PaperSize::A4,
            fitToWidth: 1,
        ));
        $options->setPageMargin(new PageMargin(top: 0.8, right: 0.6, bottom: 0.8, left: 0.6));
        $options->setColumnWidth(self::LARGHEZZA_NOME, 1);
        $options->setColumnWidth(self::LARGHEZZA_PREZZO, 2);
        $options->setColumnWidth(self::LARGHEZZA_QTY, 3);

        return $options;
    }

    private function headerStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontSize(12)
            ->setBackgroundColor('EEEEEE')
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder($this->bordoSottile());
    }

    private function bodyStyle(): Style
    {
        return (new Style)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder($this->bordoSottile());
    }

    private function priceStyle(): Style
    {
        return (new Style)
            ->setCellAlignment(CellAlignment::RIGHT)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setFormat('#,##0.00\ "€"')
            ->setBorder($this->bordoSottile());
    }

    private function qtyStyle(): Style
    {
        return (new Style)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder($this->bordoSottile());
    }

    private function bordoSottile(): Border
    {
        return new Border(
            new BorderPart(Border::TOP, '000000', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, '000000', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, '000000', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::LEFT, '000000', Border::WIDTH_THIN, Border::STYLE_SOLID),
        );
    }

    private function sheetName(Queue $queue): string
    {
        $nome = trim((string) preg_replace('/[\[\]:*?\/\\\\]/', ' ', $queue->label));

        return Str::limit($nome, self::MAX_NOME_FOGLIO, '') ?: 'Fila '.$queue->getKey();
    }
}
