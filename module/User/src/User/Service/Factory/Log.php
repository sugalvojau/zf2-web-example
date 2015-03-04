<?php
/**
 * Created by PhpStorm.
 * User: Paulius
 * Date: 2015-03-04
 * Time: 21:31
 */

namespace User\Service\Factory;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream as StreamWriter;
use Zend\Log\Filter\Priority as PriorityFilter;


class Log implements FactoryInterface {
    /**
     * Create service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('config');
        // To start logging we need to create an instance of Zend\Log\Logger
        $log = new Logger();
        // And we must add to the logger at least one writer
        $writer = new StreamWriter('php://stderr');
        $log->addWriter($writer);

        // Using priority filter:
        $priority = @$config['log']['priority'];
        if($priority !== null) {
            $filter = new PriorityFilter($priority);
            $writer->addFilter($filter);
        }

        return $log;

    }

}