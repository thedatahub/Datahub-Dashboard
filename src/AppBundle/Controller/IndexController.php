<?php
namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class IndexController extends Controller
{
    private function getBasicData($currentPage)
    {
        return array(
            'service_name' => $this->getParameter('service_name'),
            'service_address' => $this->getParameter('service_address'),
            'route_manual' => $this->generateUrl('manual'),
            'route_about' => $this->generateUrl('about'),
            'route_open_source' => $this->generateUrl('open_source'),
            'route_open_data' => $this->generateUrl('open_data'),
            'current_page' => $currentPage
        );
    }

    /**
     * @Route("/", name="home")
     */
    public function home()
    {
        $providers = $this->get('doctrine_mongodb')->getRepository('ProviderBundle:Provider')->findAll();

        $data = array(
            'providers' => $providers,
            'provider' => 'Kies een organisatie ...',
        );
        return $this->render("home.html.twig", $data + $this->getBasicData('dashboard'));
    }

    /**
     * @Route("/{_locale}/manual", name="manual", requirements={"_locale" = "%app.locales%"})
     */
    public function manual()
    {
        return $this->render("base.html.twig", $this->getBasicData('manual'));
    }

    /**
     * @Route("/{_locale}/about", name="about", requirements={"_locale" = "%app.locales%"})
     */
    public function about()
    {
        return $this->render("base.html.twig", $this->getBasicData('about'));
    }

    /**
     * @Route("/{_locale}/open_source", name="open_source", requirements={"_locale" = "%app.locales%"})
     */
    public function openSource()
    {
        return $this->render("base.html.twig", $this->getBasicData('open_source'));
    }

    /**
     * @Route("/{_locale}/open_data", name="open_data", requirements={"_locale" = "%app.locales%"})
     */
    public function openData()
    {
        return $this->render("base.html.twig", $this->getBasicData('open_data'));
    }
}
