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

namespace libredte\lib\Core\Package\Billing\Component\Document\Support;

use Derafu\Lib\Core\Support\Store\Contract\DataContainerInterface;
use Derafu\Lib\Core\Support\Store\DataContainer;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentBatchInterface;

/**
 * Contenedor de datos para procesamiento en lote de documentos tributarios.
 *
 * Permite "mover" varios documentos, junto a otros datos asociados, por métodos
 * de manera sencilla y, sobre todo, extensible.
 */
class DocumentBatch implements DocumentBatchInterface
{
    /**
     * Ruta al archivo que contiene el lote de documentos que se deben procesar.
     *
     * @var string
     */
    private string $file;

    /**
     * Opciones para los workers asociados al procesamiento en lote de
     * documentos.
     *
     * Se definen los siguientes índices para las opciones:
     *
     *   - `batch_processor`: Opciones para el procesador en lote de documentos.
     *   - `builder`: Opciones para los constructores.
     *   - `normalizer`: Opciones para los normalizadores.
     *   - `parser`: Opciones para los analizadores sintácticos.
     *   - `renderer`: Opciones para los renderizadores.
     *   - `sanitizer`: Opciones para los sanitizadores.
     *   - `validator`: Opciones para los validadores.
     *
     * Se usarán las opciones por defecto en cada worker si no se indican los
     * índices en el arreglo $options.
     *
     * @var DataContainerInterface|null
     */
    private ?DataContainerInterface $options;

    /**
     * Reglas de esquema de las opciones del lote de documentos.
     *
     * El formato del esquema es el utilizado por
     * Symfony\Component\OptionsResolver\OptionsResolver.
     *
     * Acá solo se indicarán los índices que deben pueden existir en las
     * opciones. No se define el esquema de cada opción pues cada clase que
     * utilice estas opciones deberá resolver y validar sus propias opciones.
     *
     * @var array
     */
    protected array $optionsSchema = [
        'batch_processor' => [
            'types' => 'array',
            'default' => [],
        ],
        'builder' => [
            'types' => 'array',
            'default' => [],
        ],
        'normalizer' => [
            'types' => 'array',
            'default' => [],
        ],
        'parser' => [
            'types' => 'array',
            'default' => [],
        ],
        'renderer' => [
            'types' => 'array',
            'default' => [],
        ],
        'sanitizer' => [
            'types' => 'array',
            'default' => [],
        ],
        'validator' => [
            'types' => 'array',
            'default' => [],
        ],
    ];

    public function __construct(
        string $file,
        array|DataContainerInterface|null $options = null
    ) {
        $this->file = $file;
        $this->setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array|DataContainerInterface|null $options): static
    {
        if ($options === null) {
            $options = [];
        }

        if (is_array($options)) {
            $options = new DataContainer($options, $this->optionsSchema);
        }

        $this->options = $options;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): ?DataContainerInterface
    {
        return $this->options;
    }
}
