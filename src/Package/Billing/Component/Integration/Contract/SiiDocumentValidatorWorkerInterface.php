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

namespace libredte\lib\Core\Package\Billing\Component\Integration\Contract;

use Derafu\Lib\Core\Foundation\Contract\WorkerInterface;
use Derafu\Lib\Core\Package\Prime\Component\Certificate\Contract\CertificateInterface;
use libredte\lib\Core\Package\Billing\Component\Integration\Exception\SiiDocumentValidatorException;
use libredte\lib\Core\Package\Billing\Component\Integration\Response\SiiDocumentValidationResponse;
use libredte\lib\Core\Package\Billing\Component\Integration\Response\SiiDocumentValidationSignatureResponse;

/**
 * Interfaz del worker que permite validar documentos tributarios en el SII.
 */
interface SiiDocumentValidatorWorkerInterface extends WorkerInterface
{
    /**
     * Obtiene el estado de un documento en el SII.
     *
     * Este estado solo se obtiene si el documento se encuentra aceptado por el
     * SII, ya sea aceptado 100% OK o con reparos.
     *
     * Este servicio valida que el documento exista en SII (esté aceptado) y
     * además que los datos del documento proporcionados coincidan.
     *
     * Referencia: https://www.sii.cl/factura_electronica/factura_mercado/estado_dte.pdf
     *
     * @param CertificateInterface $certificate Certificado digital del usuario.
     * @param string $company RUT de la empresa emisora del documento.
     * @param int $document Tipo de documento tributario electrónico.
     * @param int $number Folio del documento.
     * @param string $date Fecha de emisión del documento, formato: AAAA-MM-DD.
     * @param int $total Total del documento.
     * @param string $recipient RUT del receptor del documento.
     * @return SiiDocumentValidationResponse
     * @throws SiiDocumentValidatorException En caso de error.
     */
    public function validate(
        CertificateInterface $certificate,
        string $company,
        int $document,
        int $number,
        string $date,
        int $total,
        string $recipient
    ): SiiDocumentValidationResponse;

    /**
     * Obtiene el estado avanzado de un documento en el SII.
     *
     * Este estado solo se obtiene si el documento se encuentra aceptado por el
     * SII, ya sea aceptado 100% OK o con reparos.
     *
     * Este servicio valida que el documento exista en SII (esté aceptado), que
     * los datos del documento proporcionados coincidan. Finalmente, valida que
     * la firma electrónica del documento coincida con la enviada al SII.
     *
     * Referencia: https://www.sii.cl/factura_electronica/factura_mercado/OIFE2006_QueryEstDteAv_MDE.pdf
     *
     * @param CertificateInterface $certificate Certificado digital del usuario.
     * @param string $company RUT de la empresa emisora del documento.
     * @param int $document Tipo de documento tributario electrónico.
     * @param int $number Folio del documento.
     * @param string $date Fecha de emisión del documento, formato: AAAA-MM-DD.
     * @param int $total Total del documento.
     * @param string $recipient RUT del receptor del documento.
     * @param string $signature Tag DTE/Signature/SignatureValue del XML.
     * @return SiiDocumentValidationSignatureResponse
     * @throws SiiDocumentValidatorException En caso de error.
     */
    public function validateSignature(
        CertificateInterface $certificate,
        string $company,
        int $document,
        int $number,
        string $date,
        int $total,
        string $recipient,
        string $signature
    ): SiiDocumentValidationSignatureResponse;
}