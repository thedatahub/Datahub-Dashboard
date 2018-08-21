<?php
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 6/28/18
 * Time: 10:43 AM
 */

namespace AppBundle\Menu;

use Knp\Menu\FactoryInterface;

class MenuBuilder
{
    private $factory;

    public function __construct(FactoryInterface $factory) {
        $this->factory = $factory;
    }

    public function createMainMenu() {
        $menu = $this->factory->createItem('root');

        $menu->setChildrenAttributes(array('class' => 'nav navbar-nav main-nav navbar-right'));

        $menu->addChild('Dashboard', ['route' => 'home']);
        $menu->addChild('Manual', ['route' => 'manual']);
        $menu->addChild('About', ['route' => 'about']);
        $menu->addChild('Open Source', ['route' => 'open_source']);
        $menu->addChild('Open Data', ['route' => 'open_data']);

        return $menu;
    }
}
