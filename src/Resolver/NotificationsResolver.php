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
    protected $notifications = array();

    /**
     * Queue of services for which notifications should be resolved in the next
     * call to `::resolveQueue()`.
     * 
     * @var array
     */
    protected $queue = array();

    private $resolving = false;

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
            if ($serviceDefinition->isInstantiated()) {
                $service = $serviceDefinition->getInstance();
                return $this->deliverNotification($service, $senderName, $methodName, $arguments);
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
     * Queues a service for receiving notifications when `::resolveQueue()` is called.
     * 
     * @param  Service $service  Service definition.
     * @param  object  $instance Instance of the target service.
     */
    public function queueForResolving(Service $service, $instance) {
        // store in the queue under a hash so that one service doesn't get queued twice
        $this->queue[spl_object_hash($instance)] = array(
            'service' => $service,
            'instance' => $instance
        );
    }

    public function resolveQueue() {
        if ($this->resolving) {
            return;
        }

        $this->resolving = true;

        while(($item = array_shift($this->queue)) !== null) {
            $service = $item['service'];
            $serviceName = $service->getName();
            $instance = $item['instance'];

            // @todo find known aliases for this item
            if (!isset($this->notifications[$serviceName])) {
                continue;
            }

            while(($notification = array_shift($this->notifications[$serviceName])) !== null) {
                $this->deliverNotification($instance, $notification['sender'], $notification['method'], $notification['arguments']);
            }
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
        call_user_func_array(array($targetService, $methodName), $arguments);
        return true;
    }

    public function isResolvingQueue() {
        return $this->resolving;
    }

}