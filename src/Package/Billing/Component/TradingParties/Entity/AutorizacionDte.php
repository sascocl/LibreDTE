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

namespace libredte\lib\Core\Package\Billing\Component\TradingParties\Entity;

use libredte\lib\Core\Package\Billing\Component\Integration\Entity\Ambiente;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\AutorizacionDteInterface;

/**
 * Entidad para representar la información de autorización que da el SII a un
 * contribuyente para ser emisor de documentos tributarios electrónicos.
 *
 * Cada contribuyente puede tener 2 autorizaciones, una por cada ambiente.
 */
class AutorizacionDte implements AutorizacionDteInterface
{
    /**
     * Constructor de la entidad.
     *
     * @param string $fechaResolucion Fecha asignada por SII a la resolución.
     * @param integer $numeroResolucion Número de resolución.
     */
    public function __construct(
        private string $fechaResolucion,
        private int $numeroResolucion = 0
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getFechaResolucion(): string
    {
        return $this->fechaResolucion;
    }

    /**
     * {@inheritdoc}
     */
    public function getNumeroResolucion(): int
    {
        return $this->numeroResolucion;
    }

    /**
     * {@inheritdoc}
     */
    public function getAmbiente(): Ambiente
    {
        return $this->numeroResolucion === 0
            ? Ambiente::PRODUCCION
            : Ambiente::CERTIFICACION
        ;
    }
}