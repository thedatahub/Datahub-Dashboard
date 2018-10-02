<?php
namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class IndexController extends Controller
{
    private function getBasicData($currentPage, $request)
    {
        $translatedRoutes = array();
        foreach(explode('|', $this->getParameter('app.locales')) as $locale) {
            $translatedRoute = array(
                'locale'=> $locale,
                'route' => $this->generateUrl($currentPage, array('_locale' => $locale))
            );
            if($locale === $request->getLocale()) {
                $translatedRoute['active'] = true;
            }
            $translatedRoutes[] = $translatedRoute;
        }
        return array(
            'service_name' => $this->getParameter('service_name'),
            'service_address' => $this->getParameter('service_address'),
            'route_home' => $this->generateUrl('home'),
            'route_manual' => $this->generateUrl('manual'),
            'route_open_data' => $this->generateUrl('open_data'),
            'route_open_source' => $this->generateUrl('open_source'),
            'route_legal' => $this->generateUrl('legal'),
            'current_page' => $currentPage,
            'translated_routes'=> $translatedRoutes
        );
    }

    /**
     * @Route("/");
     * @Route("/{_locale}", name="home", requirements={"_locale" = "%app.locales%"})
     */
    public function homeWithLocale(Request $request)
    {
        $providers = $this->get('doctrine_mongodb')->getRepository('ProviderBundle:Provider')->findAll();

        $data = array(
            'providers' => $providers,
            'provider' => $this->get('translator')->trans('choose_provider')
        );
        return $this->render("home." . $request->getLocale() . ".html.twig", $data + $this->getBasicData('home', $request));
    }

    /**
     * @Route("/{_locale}/manual", name="manual", requirements={"_locale" = "%app.locales%"})
     */
    public function manual(Request $request)
    {
        return $this->render("manual." . $request->getLocale() . ".html.twig", $this->getBasicData('manual', $request));
    }

    /**
     * @Route("/{_locale}/open_data", name="open_data", requirements={"_locale" = "%app.locales%"})
     */
    public function openData(Request $request)
    {
        return $this->render("open_data." . $request->getLocale() . ".html.twig", $this->getBasicData('open_data', $request));
    }

    /**
     * @Route("/{_locale}/open_source", name="open_source", requirements={"_locale" = "%app.locales%"})
     */
    public function openSource(Request $request)
    {
        return $this->render("open_source." . $request->getLocale() . ".html.twig", $this->getBasicData('open_source', $request));
    }

    /**
     * @Route("/{_locale}/legal", name="legal", requirements={"_locale" = "%app.locales%"})
     */
    public function legal(Request $request)
    {
        return $this->render("legal." . $request->getLocale() . ".html.twig", $this->getBasicData('legal', $request));
    }
}
