<?php
namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class IndexController extends Controller
{
    private function getBasicData()
    {
        return array('title' => $this->getParameter('title'), 'email' => $this->getParameter('email'));
    }

    /**
     * @Route("/", name="home")
     */
    public function home()
    {
        $reportUrl = $this->generateUrl('report');
        $providers = $this->get('doctrine_mongodb')->getRepository('ProviderBundle:Provider')->findAll();

        $data = array(
            'report_url' => $reportUrl,
            'providers' => $providers,
            'provider' => 'Kies een organisatie ...'
        );
        return $this->render("home.html.twig", $data + $this->getBasicData());
    }

    /**
     * @Route("/manual", name="manual")
     */
    public function manual()
    {
        return $this->render("base.html.twig", $this->getBasicData());
    }

    /**
     * @Route("/about", name="about")
     */
    public function about()
    {
        return $this->render("base.html.twig", $this->getBasicData());
    }

    /**
     * @Route("/open_source", name="open_source")
     */
    public function openSource()
    {
        return $this->render("base.html.twig", $this->getBasicData());
    }

    /**
     * @Route("/open_data", name="open_data")
     */
    public function openData()
    {
        return $this->render("base.html.twig", $this->getBasicData());
    }
}
