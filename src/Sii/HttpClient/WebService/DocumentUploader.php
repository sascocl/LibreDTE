<?php

declare(strict_types=1);

/**
 * LibreDTE: Biblioteca PHP (Núcleo).
 * Copyright (C) LibreDTE <https://www.libredte.cl>
 *
 * Este programa es software libre: usted puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General Affero de GNU publicada
 * por la Fundación para el Software Libre, ya sea la versión 3 de la Licencia,
 * o (a su elección) cualquier versión posterior de la misma.
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

namespace libredte\lib\Core\Sii\HttpClient\WebService;

use libredte\lib\Core\Helper\Rut;
use libredte\lib\Core\Signature\Certificate;
use libredte\lib\Core\Sii\HttpClient\ConnectionConfig;
use libredte\lib\Core\Sii\HttpClient\SiiClientException;
use libredte\lib\Core\Sii\HttpClient\TokenManager;
use libredte\lib\Core\Xml\XmlConverter;
use libredte\lib\Core\Xml\XmlDocument;
use UnexpectedValueException;

/**
 * Clase para el envío de documentos al SII.
 *
 * Principalmente es para el envío y consulta de estado del envío de documentos
 * tributarios electrónicos en formato XML.
 */
class DocumentUploader
{
    /**
     * Certificado digital.
     *
     * @var Certificate
     */
    private Certificate $certificate;

    /**
     * Configuración de la conexión al SII.
     *
     * @var ConnectionConfig
     */
    private ConnectionConfig $config;

    /**
     * Administrador de tokens de autenticación del SII.
     *
     * @var TokenManager
     */
    private TokenManager $tokenManager;

    /**
     * Constructor de la clase que consume servicios web mediante WSDL del SII.
     *
     * @param Certificate $certificate
     * @param ConnectionConfig $config
     * @param TokenManager $tokenManager
     */
    public function __construct(
        Certificate $certificate,
        ConnectionConfig $config,
        TokenManager $tokenManager,
    ) {
        $this->certificate = $certificate;
        $this->config = $config;
        $this->tokenManager = $tokenManager;
    }

    /**
     * Realiza el envío de un XML al SII.
     *
     * @param XmlDocument $doc Documento XML que se desea enviar al SII.
     * @param string $company RUT de la empresa emisora del XML.
     * @param bool $compress Indica si se debe enviar comprimido el XML.
     * @param int|null $retry Intentos que se realizarán como máximo al enviar.
     * @return int Número de seguimiento (Track ID) del envío del XML al SII.
     * @throws UnexpectedValueException Si alguno de los RUT son inválidos.
     */
    public function sendXml(
        XmlDocument $doc,
        string $company,
        bool $compress = false,
        ?int $retry = null
    ): int {
        // Crear string del documento XML.
        $xml = $doc->saveXML();
        if (empty($xml) || $xml == '<?xml version="1.0" encoding="ISO-8859-1"?>'."\n") {
            throw new SiiClientException(
                'El XML que se desea enviar al SII no puede ser vacío.'
            );
        }

        // Validar los RUT que se utilizarán para el envío y descomponerlos.
        $sender = $this->certificate->getID();
        Rut::validate($sender);
        Rut::validate($company);
        [$rutSender, $dvSender] = Rut::toArray($sender);
        [$rutCompany, $dvCompany] = Rut::toArray($company);

        // Crear el archivo que se enviará en el sistema de archivos para poder
        // adjuntarlo en la solicitud mediante curl al SII.
        [$filepath, $mimetype] = $this->createXmlFile($xml, $compress);
        $filename = $company . '_' . basename($filepath);

        // Preparar los datos que se enviarán mediante POST al SII.
        $data = [
            'rutSender' => $rutSender,
            'dvSender' => $dvSender,
            'rutCompany' => $rutCompany,
            'dvCompany' => $dvCompany,
            'archivo' => curl_file_create($filepath, $mimetype, $filename),
        ];

        // Si no se especificó $retry se obtiene el valor por defecto.
        $retry = max(0, min(
            $retry ?? $this->config->getReintentos(),
            ConnectionConfig::REINTENTOS
        ));

        // Realizar la solicitud mediante POST al SII para subir el archivo.
        $xmlResponse = $this->uploadXml($data, $retry);

        // Eliminar el archivo temporal con el XML.
        unlink($filepath);

        // Procesar respuesta recibida desde el SII.
        $response = XmlConverter::xmlToArray($xmlResponse);
        $this->validateUploadXmlResponse($response);

        // Entregar el número de seguimiendo (Track ID) del envío al SII.
        $trackId = $response['RECEPCIONDTE']['TRACKID'] ?? 0;
        return (int) $trackId;
    }

    /**
     * Valida la respuesta recibida desde el SII al enviar un XML.
     *
     * @param array $response Arreglo con los datos del XML de la respuesta.
     * @return void
     * @throws SiiClientException Si el envío tuvo algún problema.
     */
    private function validateUploadXmlResponse(array $response): void
    {
        $status = $response['RECEPCIONDTE']['STATUS'] ?? null;

        // Si el estado es `null` la respuesta del SII no es válida. Lo cual
        // indicaría que el SII no contestó correctamente a la solicitud o bien
        // la misma solicitud se hizo de manera incorrecta produciendo que el
        // SII no contestase adecuadamente.
        if ($status === null) {
            throw new SiiClientException(
                'La respuesta del envío del XML al SII no trae un código de estado válido.'
            );
        }

        // Si el estado es 0, el envío fue OK.
        if ($status == 0) {
            return;
        }

        // Se define un mensaje de error según el código de estado.
        switch ($status) {
            case 1:
                $message = sprintf(
                    'El usuario %s no tiene permisos para enviar XML al SII.',
                    $this->certificate->getId()
                );
                break;
            case 2:
                $message = 'Error en el tamaño del archivo enviado con el XML, muy grande o muy chico.';
                break;
            case 3:
                $message = 'El archivo enviado está cortado, el tamaño es diferente al parámetro "size".';
                break;
            case 5:
                $message = sprintf(
                    'El usuario %s no está autenticado (posible token expirado).',
                    $this->certificate->getId()
                );
                break;
            case 6:
                $message = 'La empresa no está autorizada a enviar archivos XML al SII.';
                break;
            case 7:
                $message = 'El esquema del XML es inválido.';
                break;
            case 8:
                $message = 'Existe un error en la firma del documento XML.';
                break;
            case 9:
                $message = 'Los servidores del SII están con problemas internos.';
                break;
            case 99:
                $message = 'El XML enviado ya fue previamente recibido por el SII.';
                break;
            default:
                $message = sprintf(
                    'Ocurrió un error con código de estado "%s", que es desconocido por LibreDTE.',
                    $status
                );
                break;
        }

        // Ver si vienen detalles del error.
        $error = $response['DETAIL']['ERROR'] ?? null;
        if ($error !== null) {
            $message .= ' ' . implode(' ', $error);
        }

        // Lanzar una excepción con el mensaje de error determinado.
        throw new SiiClientException($message);
    }

    /**
     * Sube el archivo XML al SII y retorna la respuesta de este.
     *
     * Este método emula la subida mendiante los siguientes formularios:
     *
     *   - Producción: https://palena.sii.cl/cgi_dte/UPL/DTEauth?1
     *   - Certificación: https://maullin.sii.cl/cgi_dte/UPL/DTEauth?1
     *
     * @param array $data Arreglo con los datos del formulario del SII,
     * incluyendo el archivo XML que se subirá.
     * @param integer $retry
     * @return XmlDocument Respuesta del SII al enviar el XML.
     * @throws SiiClientException Si no se puede obtener el token para enviar
     * el XML al SII o si hubo un problema (error) al enviar el XML al SII.
     */
    private function uploadXml(array $data, int $retry): XmlDocument
    {
        // URL que se utilizará para subir el XML al SII.
        $url = $this->config->getUrl('/cgi_dte/UPL/DTEUpload');

        // Obtener el token asociado al certificado digital.
        $token = $this->tokenManager->getToken($this->certificate);

        // Cabeceras HTTP de la solicitud que se hará al SII.
        $headers = [
            'User-Agent: Mozilla/4.0 (compatible; PROG 1.0; LibreDTE)',
            'Referer: https://www.libredte.cl',
            'Cookie: TOKEN=' . $token,
        ];

        // Inicializar curl.
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // Si no se debe verificar el certificado SSL del servidor del SII se
        // agrega la opción a curl.
        if (!$this->config->getVerificarSsl()) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        }

        // Realizar el envío del XML al SII con $retry intentos.
        $responseBody = null;
        for ($i = 0; $i < $retry; $i++) {
            // Realizar consulta al SII enviando el XML.
            $responseBody = curl_exec($curl);

            // Si se logró obtener una respuesta, y no es un "Error 500",
            // entonces se logró enviar el XML al SII y se rompe el ciclo para
            // parar los reintentos.
            if ($responseBody && $responseBody !== 'Error 500') {
                break;
            }

            // El reitento será con "exponential backoff", por lo que se hace
            // una pausa de 0.2 * $retry segundos antes de volver a intentar el
            // envio del XML al SII.
            usleep(200000 * $retry);
        }

        // Validar si hubo un error en la respuesta.
        if (!$responseBody || $responseBody === 'Error 500') {
            $message = 'Falló el envío del XML al SII. ';
            $message = !$responseBody
                ? curl_error($curl)
                : 'El SII tiene problemas en sus servidores (Error 500).'
            ;
            curl_close($curl); // Se cierra conexión curl acá por error.
            throw new SiiClientException($message);
        }

        // Cerrar conexión curl.
        curl_close($curl);

        // Entregar el resultado como un documento XML.
        $xmlDocument = new XmlDocument();
        $xmlDocument->loadXML($responseBody);
        return $xmlDocument;
    }

    /**
     * Guarda el XML en un archivo temporal y, si es necesario, lo comprime.
     *
     * @param string $xml Documento XML que se guardará en el archivo..
     * @param bool $compress Indica si se debe crear un archivo comprimido.
     * @return array Arreglo con la ruta al archivo creado y su mimetype.
     */
    private function createXmlFile(string $xml, bool $compress): array
    {
        // Normalizar el XML agregando el encabezado si no viene en el
        // documento. El SII recibe los documentos en ISO-8859-1 por lo que se
        // asume (y no valida) que el contenido del XML en $xml viene ya
        // codificado en ISO-8859-1.
        if (!str_contains($xml, '<?xml')) {
            $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n" . $xml;
        }

        // Comprimir el XML si es necesario. En caso de error al comprimir se
        // creará el archivo XML igualmente, pero sin comprimir. Ya que no
        // debería fallar la creación del XML para el envío si falló la
        // compresión al ser una funcionalidad opcional del SII.
        if ($compress) {
            $xmlGzEncoded = gzencode($xml);
            if ($xmlGzEncoded !== false) {
                $xml = $xmlGzEncoded;
            } else {
                $compress = false;
            }
        }

        // Crear archivo temporal y guardar los datos del XML en el archivo.
        $filepath = $this->getXmlFilePath($compress);
        file_put_contents($filepath, $xml);

        // Determinar mimetype que tiene el archivo.
        $mimetype = $compress ? 'application/gzip' : 'application/xml';

        // Entregar la ruta al archivo creado con el contenido del XML y su
        // mimetype final (pues no necesariamente será gzip aunque se haya así
        // solicitado).
        return [$filepath, $mimetype];
    }

    /**
     * Obtiene un nombre único para el archivo del XML que se desea crear.
     *
     * @param bool $compress Indica si se debe crear un archivo comprimido.
     * @return string Arreglo con la ruta al archivo.
     */
    private function getXmlFilePath(bool $compress): string
    {
        // Genera un archivo temporal para el XML.
        $tempDir = sys_get_temp_dir();
        $prefix = 'libredte_xml_document_for_upload_to_sii_';
        $filepath = tempnam($tempDir, $prefix);

        // Renombrar la ruta asignando la extensión al archivo.
        $realFilepath = $filepath . ($compress ? '.xml.gz' : '.xml');
        rename($filepath, $realFilepath);

        // Entregar la ruta que se determinó para el archivo.
        return $realFilepath;
    }
}