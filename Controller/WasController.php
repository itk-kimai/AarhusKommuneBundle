<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\AarhusKommuneBundle\Configuration\AarhusKommuneConfiguration;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WasController extends AbstractController
{
    public function __construct(
        private readonly AarhusKommuneConfiguration $configuration,
    )
    {
    }

    public function __invoke(): Response
    {
        $url = $this->configuration->getWasUrl();

        if (null === $url) {
            throw new NotFoundHttpException();
        }

        return new RedirectResponse($url);
    }
}
