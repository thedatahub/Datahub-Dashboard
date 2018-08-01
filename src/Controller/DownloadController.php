<?php
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 7/31/18
 * Time: 4:22 PM
 */

namespace App\Controller;


use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class DownloadController extends AbstractController {
    /**
     * @Route("/download", name="download")
     */
    public function download(Request $request) {
        $this->provider = urldecode($request->query->get('provider'));
        $aspect = urldecode($request->query->get('aspect'));
        $parameter = urldecode($request->query->get('parameter'));
        $question = urldecode($request->query->get('question'));
        $chart = urldecode($request->query->get('chart'));

        // Generate response
        $response = new Response();
        $filename = $aspect . '_' . $parameter . '_' . $question . '.csv';

        //TODO generate data
        $csvData = '';

        // Set headers
        $response->headers->set('Cache-Control', 'private');
        $response->headers->set('Content-type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '";');
        $response->headers->set('Content-length',  strlen($csvData));
        $response->sendHeaders();

        $response->setContent($csvData);

        return $response;
    }
}