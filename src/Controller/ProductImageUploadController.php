<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ProductImageUploadController extends AbstractController
{
    public function __invoke(Product $product, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $file = $request->files->get('image');

        if (!$file) {
            return $this->json(['error' => 'Aucun fichier reçu'], 400);
        }

        $product->setImageFile($file);
        $em->flush();
        return $this->json(["imageName" => $product->getImageName()], 200);
    }
}
