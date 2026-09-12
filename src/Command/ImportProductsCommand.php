<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:import-products')]
class ImportProductsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Chemin vers le fichier JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $output->writeln("<error>Fichier introuvable : $filePath</error>");
            return Command::FAILURE;
        }

        $products = json_decode(file_get_contents($filePath), true);

        if (!is_array($products)) {
            $output->writeln('<error>JSON invalide.</error>');
            return Command::FAILURE;
        }

        $httpClient = HttpClient::create();
        $categoriesCache = [];
        $success = 0;
        $failed = 0;

        foreach ($products as $index => $data) {
            $output->writeln(sprintf('[%d/%d] %s', $index + 1, count($products), $data['name']));

            try {
                // 1. Récupère ou crée la catégorie (avec cache pour éviter les doublons/requêtes répétées)
                $categoryName = $data['category'];
                if (!isset($categoriesCache[$categoryName])) {
                    $category = $this->em->getRepository(Category::class)->findOneBy(['name' => $categoryName]);
                    if (!$category) {
                        $category = new Category();
                        $category->setName($categoryName);
                        $this->em->persist($category);
                    }
                    $categoriesCache[$categoryName] = $category;
                }
                $category = $categoriesCache[$categoryName];

                // 2. Convertit le prix "12,95" -> "12.95"
                $price = str_replace(',', '.', $data['price']);

                // 3. Télécharge l'image dans un fichier temporaire
                $tmpFile = $this->downloadImage($httpClient, $data['image']);

                // 4. Crée le produit
                $product = new Product();
                $product->setName($data['name']);
                $product->setDescription($data['description']);
                $product->setPrice($price);
                $product->setCategory($category);

                if ($tmpFile) {
                    $product->setImageFile($tmpFile);
                }

                $this->em->persist($product);
                $this->em->flush(); // flush produit par produit pour isoler les erreurs
                $output->writeln('imageName en mémoire : ' . ($product->getImageName() ?? 'NULL'));


                $success++;
            } catch (\Throwable $e) {
                $output->writeln(sprintf('  <error>Échec : %s</error>', $e->getMessage()));
                $failed++;
                continue; // on n'arrête pas tout l'import pour un seul produit en erreur
            }
        }

        $output->writeln(sprintf('<info>Terminé : %d réussis, %d échoués.</info>', $success, $failed));

        return Command::SUCCESS;
    }

    private function downloadImage(HttpClientInterface $httpClient, string $url): ?UploadedFile
    {
        $response = $httpClient->request('GET', $url);
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('import_') . '.' . $extension;

        file_put_contents($tmpPath, $content);

        return new UploadedFile(
            $tmpPath,
            basename($tmpPath),
            null,   // mimeType, laisse Symfony le détecter
            null,   // error code
            true    // test mode
        );
    }
}
