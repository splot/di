<?php
namespace Splot\DependencyInjection\Resolver;

use Splot\DependencyInjection\Exceptions\ServiceNotFoundException;
use Splot\DependencyInjection\Definition\Service;
use Splot\DependencyInjection\Resolver\ArgumentsResolver;
use Splot\DependencyInjection\Container;

class NotificationsResolver
{

    /**
     * The container.
     * 
     * @var Container
     */
    protected $container;

    /**
     * Arguments resolver.
     * 
     * @var ArgumentsResolver
     */
    protected $argumentsResolver;

    /**
     * All notifications that have been registered, indexed by target service name.
     * 
     * @var array
     */
    public $notifications = array();

    /**
     * Queue of services for which notifications should be resolved in the next
     * call to `::resolveQueue()`.
     * 
     * @var array
     */
    public $queue = array();

    /**
     * Flag for marking that notifications queue is currently being resolved.
     * 
     * @var boolean
     */
    public $resolving = false;

    /**
     * Constructor.
     * 
     * @param Container         $container         The container.
     * @param ArgumentsResolver $argumentsResolver Arguments resolver.
     */
    public function __construct(Container $container, ArgumentsResolver $argumentsResolver) {
        $this->container = $container;
        $this->argumentsResolver = $argumentsResolver;
    }

    /**
     * Registers a notification to be sent in the future, when service with name `$targetServiceName`
     * will be instantiated by the container or immediatelly if its already instantiated.
     *
     * Returns `true` if the notification was delivered immediatelly, `false` if not (either couldn't
     * be delivered immediatelly, or was enqueued for later).
     * 
     * @param  string $senderName        Name of the service that sent this notification.
     * @param  string $targetServiceName Name of the service that should receive this notification.
     * @param  string $methodName        Name of the method on the target service that should be called.
     * @param  array  $arguments         [optional] Method arguments.
     * @return boolean
     */
    public function registerNotification($senderName, $targetServiceName, $methodName, array $arguments = array()) {
        // check if maybe such service is already registered and instantiated,
        // then deliver this notification immediatelly
        try {
            $serviceDefinition = $this->container->getDefinition($targetServiceName);
            
            // $targetServiceName might be an alias, so let's use the real name
            $targetServiceName = $serviceDefinition->name;

            if ($serviceDefinition->isInstantiated()) {
                return $this->deliverNotification($serviceDefinition->instance, $senderName, $methodName, $arguments);
            }
        } catch(ServiceNotFoundException $e) {}

        // but if no service was found then add this notification to the list
        $this->notifications[$targetServiceName][] = array(
            'sender' => $senderName,
            'target' => $targetServiceName,
            'method' => $methodName,
            'arguments' => $arguments
        );

        return false;
    }

    /**
     * Queues a service instance for receiving notifications when `::resolveQueue()` is called.
     * 
     * @param  string  $serviceName  Service name.
     * @param  object  $instance Instance of the target service.
     */
    public function queueForResolving($serviceName, $instance) {
        $this->queue[$serviceName] = array(
            'name' => $serviceName,
            'instance' => $instance
        );
    }

    /**
     * Resolves the current queue of services by delivering notifications to them.
     */
    public function resolveQueue() {
        if ($this->resolving) {
            return;
        }

        $this->resolving = true;

        while(($item = array_shift($this->queue)) !== null) {
            $serviceName = $item['name'];
            $instance = $item['instance'];

            if (!isset($this->notifications[$serviceName])) {
                continue;
            }

            while(($notification = array_shift($this->notifications[$serviceName])) !== null) {
                $this->deliverNotification($instance, $notification['sender'], $notification['method'], $notification['arguments']);
            }
            
            unset($this->notifications[$serviceName]);
        }

        $this->resolving = false;
    }

    /**
     * Delivers a notification by calling the given object's method with given arguments.
     *
     * Returns `false` if notification couldn't be delivered, `true` otherwise.
     * 
     * @param  object $targetService A target service instance.
     * @param  string $senderName    Name of the service that sent this notification.
     * @param  string $methodName    Name of the method that should be called.
     * @param  array  $arguments     The method arguments. Default: `array()`.
     * @return boolean
     */
    public function deliverNotification($targetService, $senderName, $methodName, array $arguments = array()) {
        if (!is_object($targetService) || !method_exists($targetService, $methodName)) {
            return false;
        }

        $arguments = $this->argumentsResolver->resolve($arguments, $senderName);

        switch(count($arguments)) {
            case 0:
                $targetService->$methodName();
            break;

            case 1:
                $targetService->$methodName($arguments[0]);
            break;

            case 2:
                $targetService->$methodName($arguments[0], $arguments[1]);
            break;

            case 3:
                $targetService->$methodName($arguments[0], $arguments[1], $arguments[2]);
            break;

            case 4:
                $targetService->$methodName($arguments[0], $arguments[1], $arguments[2], $arguments[3]);
            break;

            case 5:
                $targetService->$methodName($arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4]);
            break;

            default:
                call_user_func_array(array($targetService, $methodName), $arguments);
        }

        return true;
    }

    /**
     * Reroutes notifications from one service name to another.
     *
     * This is especially used when a notification was referring to its target by an alias and the alias's target
     * has just become known to the container.
     * 
     * @param  string $from Original notifications target.
     * @param  string $to   New notifications target.
     */
    public function rerouteNotifications($from, $to) {
        if (!isset($this->notifications[$from])) {
            return;
        }

        // $to might also be an alias, so lets dig deeper
        $to = $this->container->resolveServiceName($to);

        foreach($this->notifications[$from] as $notification) {
            $this->notifications[$to][] = $notification;
        }
    }

}
