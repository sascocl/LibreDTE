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

use Derafu\Lib\Core\Package\Prime\Component\Certificate\Contract\CertificateInterface;
use Derafu\Lib\Core\Package\Prime\Component\Xml\Contract\XmlInterface;
use Derafu\Lib\Core\Support\Store\Contract\DataContainerInterface;
use Derafu\Lib\Core\Support\Store\DataContainer;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentBagInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\TipoDocumentoInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Exception\DocumentException;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\EmisorInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\ReceptorInterface;
use stdClass;

/**
 * Contenedor de datos del documento tributario electrónico.
 *
 * Permite "mover" un documento, junto a otros datos asociados, por métodos de
 * manera sencilla y, sobre todo, extensible.
 */
class DocumentBag implements DocumentBagInterface
{
    /**
     * Datos originales de entrada que se utilizarán para construir el
     * documento tributario.
     *
     * El formato de estos datos puede ser cualquiera soportado por los parsers.
     *
     * @var string|null
     */
    private ?string $inputData;

    /**
     * Datos de entrada procesados (parseados).
     *
     * Están en el formato estándar de LibreDTE. Que es básicamente el oficial
     * del SII. Con algunas extensiones, como los datos "extras".
     *
     * Estos son los datos que se usarán para construir el documento. Estos
     * datos no están normaliados, solo parseados.
     *
     * @var array|null
     */
    private ?array $parsedData;

    /**
     * Datos normalizados del documento tributario.
     *
     * Son los datos con todos sus campos necesarios ya determinados, calculados
     * y validados.
     *
     * La estructura de estos datos depende de los normalizadores.
     *
     * Importante: si se desactiva la normalización este arreglo contendrá lo
     * mismo que $parsedData pues no se tocarán los datos de entrada procesados.
     *
     * @var array|null
     */
    private ?array $normalizedData;

    /**
     * Opciones para los workers asociados al documento.
     *
     * Se definen los siguientes índices para las opciones:
     *
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
     * Reglas de esquema de las opciones del documento.
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

    /**
     * Instancia del documento XML asociada al DTE.
     *
     * @var XmlInterface|null
     */
    private ?XmlInterface $xmlDocument;

    /**
     * Código de Asignación de Folios (CAF) para timbrar el Documento Tributario
     * Electrónico (DTE) que se generará.
     *
     * @var CafInterface|null
     */
    private ?CafInterface $caf;

    /**
     * Certificado digital (firma electrónica) para la firma del documento.
     *
     * @var CertificateInterface|null
     */
    private ?CertificateInterface $certificate;

    /**
     * Entidad con el documento tributario electrónico generado.
     *
     * @var DocumentInterface|null
     */
    private ?DocumentInterface $document;

    /**
     * Entidad que representa al tipo de documento tributario que está contenido
     * en esta bolsa.
     *
     * @var TipoDocumentoInterface|null
     */
    private ?TipoDocumentoInterface $documentType = null;

    /**
     * Emisor del documento tributario.
     *
     * @var EmisorInterface|null
     */
    private ?EmisorInterface $emisor = null;

    /**
     * Receptor del documento tributario.
     *
     * @var ReceptorInterface|null
     */
    private ?ReceptorInterface $receptor = null;

    /**
     * Arreglo con la estructura del nodo TED del documento.
     *
     * @var array|null
     */
    private ?array $timbre = null;

    /**
     * Arreglo con los datos normalizados consolidados con el timbre y la firma
     * si existen en la bolsa.
     *
     * @var array|null
     */
    private ?array $data = null;

    /**
     * Constructor del contenedor.
     *
     * Recibe los datos en diferentes formatos para pasarlos a los setters que
     * los normalizan y asignan al contenedor.
     *
     * @param string|array|stdClass|null $inputData
     * @param array|null $parsedData
     * @param array|null $normalizedData
     * @param array|DataContainerInterface|null $options
     * @param XmlInterface|null $xmlDocument
     * @param CafInterface|null $caf
     * @param CertificateInterface|null $certificate
     * @param DocumentInterface|null $document
     * @param TipoDocumentoInterface|null $documentType
     * @param EmisorInterface|null $emisor
     * @param ReceptorInterface|null $receptor
     */
    public function __construct(
        string|array|stdClass $inputData = null,
        array $parsedData = null,
        array $normalizedData = null,
        array|DataContainerInterface $options = null,
        XmlInterface $xmlDocument = null,
        CafInterface $caf = null,
        CertificateInterface $certificate = null,
        DocumentInterface $document = null,
        TipoDocumentoInterface $documentType = null,
        EmisorInterface $emisor = null,
        ReceptorInterface $receptor = null
    ) {
        $this
            ->setInputData($inputData)
            ->setParsedData($parsedData)
            ->setNormalizedData($normalizedData)
            ->setOptions($options)
            ->setXmlDocument($xmlDocument)
            ->setCaf($caf)
            ->setCertificate($certificate)
            ->setDocument($document)
            ->setDocumentType($documentType)
            ->setEmisor($emisor)
            ->setReceptor($receptor)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function setInputData(string|array|stdClass|null $inputData): static
    {
        if ($inputData === null) {
            $this->inputData = null;

            return $this;
        }

        if (!is_string($inputData)) {
            $inputData = json_encode($inputData);
        }

        $this->inputData = $inputData;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getInputData(): ?string
    {
        return $this->inputData;
    }

    /**
     * {@inheritdoc}
     */
    public function setParsedData(?array $parsedData): static
    {
        $this->parsedData = $parsedData;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParsedData(): ?array
    {
        return $this->parsedData;
    }

    /**
     * {@inheritdoc}
     */
    public function setNormalizedData(?array $normalizedData): static
    {
        $this->normalizedData = $normalizedData;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getNormalizedData(): ?array
    {
        return $this->normalizedData;
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

    /**
     * {@inheritdoc}
     */
    public function getParserOptions(): array
    {
        return (array) $this->options?->get('parser');
    }

    /**
     * {@inheritdoc}
     */
    public function getBuilderOptions(): array
    {
        return (array) $this->options?->get('builder');
    }

    /**
     * {@inheritdoc}
     */
    public function getNormalizerOptions(): array
    {
        return (array) $this->options?->get('normalizer');
    }

    /**
     * {@inheritdoc}
     */
    public function getSanitizerOptions(): array
    {
        return (array) $this->options?->get('sanitizer');
    }

    /**
     * {@inheritdoc}
     */
    public function getValidatorOptions(): array
    {
        return (array) $this->options?->get('validator');
    }

    /**
     * {@inheritdoc}
     */
    public function getRendererOptions(): array
    {
        return (array) $this->options?->get('renderer');
    }

    /**
     * {@inheritdoc}
     */
    public function setXmlDocument(?XmlInterface $xmlDocument): static
    {
        $this->xmlDocument = $xmlDocument;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getXmlDocument(): ?XmlInterface
    {
        return $this->xmlDocument;
    }

    /**
     * {@inheritdoc}
     */
    public function setCaf(?CafInterface $caf): static
    {
        $this->caf = $caf;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCaf(): ?CafInterface
    {
        return $this->caf;
    }

    /**
     * {@inheritdoc}
     */
    public function setCertificate(?CertificateInterface $certificate): static
    {
        $this->certificate = $certificate;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCertificate(): ?CertificateInterface
    {
        return $this->certificate;
    }

    /**
     * {@inheritdoc}
     */
    public function setDocument(?DocumentInterface $document): static
    {
        $this->document = $document;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDocument(): ?DocumentInterface
    {
        return $this->document;
    }

    /**
     * {@inheritdoc}
     */
    public function setDocumentType(?TipoDocumentoInterface $documentType): static
    {
        $this->documentType = $documentType;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTipoDocumento(?TipoDocumentoInterface $tipoDocumento): static
    {
        return $this->setDocumentType($tipoDocumento);
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentType(): ?TipoDocumentoInterface
    {
        return $this->documentType;
    }

    /**
     * {@inheritdoc}
     */
    public function getTipoDocumento(): ?TipoDocumentoInterface
    {
        return $this->getDocumentType();
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentTypeId(): ?int
    {
        $TipoDTE = $this->parsedData['Encabezado']['IdDoc']['TipoDTE']
            ?? $this->normalizedData['Encabezado']['IdDoc']['TipoDTE']
            ?? $this->xmlDocument?->query('//Encabezado/IdDoc/TipoDTE')
            ?? $this->document?->getCodigo()
            ?? null
        ;

        if (!$TipoDTE) {
            throw new DocumentException(
                'Falta indicar el tipo de documento (TipoDTE) en los datos del DTE.'
            );
        }

        return (int) $TipoDTE;
    }

    /**
     * {@inheritdoc}
     */
    public function getCodigoTipoDocumento(): ?int
    {
        return $this->getDocumentTypeId();
    }

    /**
     * {@inheritdoc}
     */
    public function setEmisor(?EmisorInterface $emisor): static
    {
        $this->emisor = $emisor;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmisor(): ?EmisorInterface
    {
        return $this->emisor;
    }

    /**
     * {@inheritdoc}
     */
    public function setReceptor(?ReceptorInterface $receptor): static
    {
        $this->receptor = $receptor;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getReceptor(): ?ReceptorInterface
    {
        return $this->receptor;
    }

    /**
     * {@inheritdoc}
     */
    public function setTimbre(?array $timbre): static
    {
        $this->timbre = $timbre;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimbre(): ?array
    {
        return $this->timbre;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): ?array
    {
        // Si los datos ya estaban generados se entregan.
        if ($this->data !== null) {
            return $this->data;
        }

        // Si no hay datos normalizados se entrega `null`.
        if (!$this->getNormalizedData()) {
            return null;
        }

        // Se arma la estructura del nodo Documento.
        $tagXml = $this->getTipoDocumento()->getTagXml()->getNombre();
        $this->data = [
            'DTE' => [
                '@attributes' => [
                    'version' => '1.0',
                    'xmlns' => 'http://www.sii.cl/SiiDte',
                ],
                $tagXml => array_merge(
                    [
                        '@attributes' => [
                            'ID' => $this->getId(),
                        ],
                    ],
                    $this->getNormalizedData(),
                    (array) $this->getTimbre(),
                ),
                //'Signature' => '', // Se agrega al firmar (NO INCLUIR ACÁ).
            ],
        ];

        // Se entrega la estructura con los datos.
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return sprintf(
            'LibreDTE_%s_T%dF%d',
            $this->getNormalizedData()['Encabezado']['Emisor']['RUTEmisor'],
            $this->getNormalizedData()['Encabezado']['IdDoc']['TipoDTE'],
            $this->getNormalizedData()['Encabezado']['IdDoc']['Folio']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function withCaf(CafInterface $caf): DocumentBagInterface
    {
        $class = static::class;

        return new $class(
            inputData: $this->getInputData(),
            parsedData: $this->getParsedData(),
            normalizedData: $this->getNormalizedData(),
            options: $this->getOptions(),
            caf: $caf,
            certificate: $this->getCertificate(),
            documentType: $this->getDocumentType(),
            emisor: $this->getEmisor(),
            receptor: $this->getReceptor()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function withCertificate(
        CertificateInterface $certificate
    ): DocumentBagInterface {
        $class = static::class;

        return new $class(
            inputData: $this->getInputData(),
            parsedData: $this->getParsedData(),
            normalizedData: $this->getNormalizedData(),
            options: $this->getOptions(),
            caf: $this->getCaf(),
            certificate: $certificate,
            documentType: $this->getDocumentType(),
            emisor: $this->getEmisor(),
            receptor: $this->getReceptor()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return $this->getTipoDocumento()?->getAlias()
            ?? (
                $this->getTipoDocumento()?->getCodigo()
                    ? 'documento_' .  $this->getTipoDocumento()->getCodigo()
                    : null
            )
            ?? $this->getParsedData()['Encabezado']['IdDoc']['TipoDTE']
            ?? 'documento_desconocido'
        ;
    }
}