<?php

declare(strict_types=1);

/**
 * LibreDTE: Biblioteca PHP (Núcleo).
 * Copyright (C) LibreDTE <https://www.libredte.cl>
 *
 * Este programa es software libre: usted puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General Affero de GNU publicada por
 * la Fundación para el Software Libre, ya sea la versión 3 de la Licencia, o
 * (a su elección) cualquier versión posterior de la misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero SIN
 * GARANTÍA ALGUNA; ni siquiera la garantía implícita MERCANTIL o de APTITUD
 * PARA UN PROPÓSITO DETERMINADO. Consulte los detalles de la Licencia Pública
 * General Affero de GNU para obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de
 * GNU junto a este programa.
 *
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

namespace libredte\lib\Core\Package\Billing\Component\Document\Worker;

use Derafu\Lib\Core\Foundation\Abstract\AbstractWorker;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\BatchProcessorStrategyInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\BatchProcessorWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentBatchInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Exception\BatchProcessorException;
use Throwable;

/**
 * Clase para los procesadores de documentos en lote.
 */
class BatchProcessorWorker extends AbstractWorker implements BatchProcessorWorkerInterface
{
    /**
     * {@inheritdoc}
     */
    protected array $optionsSchema = [
        '__allowUndefinedKeys' => true,
        'strategy' => [
            'types' => 'string',
            'default' => 'spreadsheet.csv',
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function process(DocumentBatchInterface $batch): array
    {
        $options = $this->resolveOptions($batch->getOptions()->all());
        $strategy = $this->getStrategy($options->get('strategy'));
        $strategy->setOptions($options);

        assert($strategy instanceof BatchProcessorStrategyInterface);

        try {
            $documents = $strategy->process($batch);
        } catch (Throwable $e) {
            throw new BatchProcessorException(
                message: $e->getMessage(),
                documentBatch: $batch
            );
        }

        return $documents;
    }
}
