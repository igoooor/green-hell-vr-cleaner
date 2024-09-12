<?php

namespace App\Controller;

use App\Service\XmlFileCleaner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FileController extends AbstractController
{
    public function __construct(
        private XmlFileCleaner $xmlFileProcessor,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    #[Route('/', name: 'file_upload')]
    public function upload(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            /** @var UploadedFile $file */
            $file = $request->files->get('xml_file');
            $removeStonehengeObjects = $request->request->get('remove_stonehenge_objects', false);

            if ($file && $file->isValid()) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = $originalFilename.'-'.uniqid().'.'.$file->guessExtension();

                $filePath = $this->projectDir.'/public/uploads/'.$newFilename;
                try {
                    $file->move($this->projectDir.'/public/uploads', $newFilename);
                } catch (FileException $e) {
                    return new Response('Failed to upload file: '.$e->getMessage());
                }

                $processedFilePath = $this->xmlFileProcessor->process(
                    $this->projectDir.'/public/uploads/'.$newFilename,
                    (bool) $removeStonehengeObjects
                );
                $content = file_get_contents($processedFilePath);

                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                if (file_exists($processedFilePath)) {
                    unlink($processedFilePath);
                }

                return new Response($content, 200, [
                    'Content-Type' => 'application/xml',
                    'Content-Disposition' => 'attachment; filename="Updated_'.$file->getClientOriginalName().'"',
                ]);
            }
        }

        return $this->render('upload.html.twig');
    }
}
