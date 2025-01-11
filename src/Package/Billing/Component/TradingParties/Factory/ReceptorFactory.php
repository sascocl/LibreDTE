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

namespace libredte\lib\Core\Package\Billing\Component\TradingParties\Factory;

use Derafu\Lib\Core\Helper\Factory;
use Derafu\Lib\Core\Helper\Rut;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Abstract\AbstractContribuyenteFactory;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\ReceptorFactoryInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\ReceptorInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Entity\Receptor;

/**
 * Fábrica de una entidad de receptor.
 */
class ReceptorFactory extends AbstractContribuyenteFactory implements ReceptorFactoryInterface
{
    /**
     * Clase de la entidad de los receptores.
     *
     * @var string
     */
    private string $class = Receptor::class;

    /**
     * {@inheritdoc}
     */
    public function create(array $data): ReceptorInterface
    {
        $data = $this->normalizeData($data);

        [$data['rut'], $data['dv']] = Rut::toArray($data['rut']);

        return Factory::create($data, $this->class);
    }
}