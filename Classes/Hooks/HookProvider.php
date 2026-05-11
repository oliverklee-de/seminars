<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Hooks;

use OliverKlee\Seminars\Hooks\Interfaces\Hook;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Provides functions for unified hooks.
 *
 * A hook allows adding functionality at certain points of the program path. These points are
 * grouped using an interface, declaring the methods to implement to hook in.
 *
 * Hooking in
 * The FQCN of the interface is the key to `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars']`
 * where you register your hooked-in classes. Your class will be instantiated once when the first
 * point is reached and re-used for all other points.
 *
 * Implementing hook points
 * Instantiate this class with the interface you need implemented. First call to `executeHook[...]()` will
 * instantiate the registered classes. Every further call will reuse the same instances. On each
 * call provide the method required at the point in your program.
 *
 * The most recommended way to design a hook method is passing objects to manipulate. Use `executeHook()`
 * for these methods. By passing an object to the hooked-in methods, the object content can be manipulated,
 * and by this change the behavior of `seminars`.
 *
 * In some cases, when a return value is required, you may use `executeHookReturningMergedArray()` for returning complex
 * results while all hooked-in methods process the same parameters. Use `executeHookReturningModifiedValue()`, if your
 * hook shall pass the already manipulated value to the next hook (e.g., for a gating condition check).
 *
 * There is an optional index to `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars']`, provided
 * for easier conversion of existing hooks to this class.
 */
class HookProvider
{
    /**
     * @var class-string<Hook>
     */
    protected string $interfaceName;

    /**
     * Index in `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars']` of hooked-in classes
     */
    protected string $index;

    /**
     * @var list<Hook>
     */
    protected array $hookObjects = [];

    protected bool $hooksHaveBeenRetrieved = false;

    /**
     * @param class-string<Hook> $interfaceName interface the hook needs implemented
     * @param string $index index to `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars']`
     *               if not using the interface name (for backwards compatibility)
     *               (the interface name is recommended)
     *
     * @throws \UnexpectedValueException
     */
    public function __construct(string $interfaceName, string $index = '')
    {
        if (!\interface_exists($interfaceName)) {
            throw new \UnexpectedValueException(
                'The interface ' . $interfaceName . ' does not exist.',
                1565089078,
            );
        }
        if (!\in_array(Hook::class, \class_implements($interfaceName), true)) {
            throw new \UnexpectedValueException(
                'The interface ' . $interfaceName . ' does not extend ' . Hook::class . ' interface.',
                1565088963,
            );
        }

        $this->interfaceName = $interfaceName;
        $this->index = ($index === '') ? $interfaceName : $index;
    }

    /**
     * Executes the hooked-in methods.
     *
     * @param string $method the method to execute
     * @param mixed $params parameters to `$method()`
     */
    public function executeHook(string $method, ...$params): void
    {
        $this->validateHookMethod($method);

        foreach ($this->getHooks() as $hook) {
            $hook->$method(...$params);
        }
    }

    /**
     * Executes the hooked-in methods that return result arrays.
     *
     * @param string $method the method to execute
     * @param mixed $params parameters to `$method()`
     *
     * @return array the merged result arrays with numeric or string keys, may contain duplicate values
     */
    public function executeHookReturningMergedArray(string $method, ...$params): array
    {
        $this->validateHookMethod($method);

        $result = [];
        foreach ($this->getHooks() as $hook) {
            $result[] = $hook->$method(...$params);
        }

        return \array_merge([], ...$result);
    }

    /**
     * Executes the hooked-in methods that pass on a manipulated value.
     *
     * @param string $method the method to execute
     * @param mixed $value the value to manipulate by `$method()`
     * @param mixed $params parameters to `$method()`
     *
     * @return mixed the manipulated value
     */
    public function executeHookReturningModifiedValue(string $method, $value, ...$params)
    {
        $this->validateHookMethod($method);

        $result = $value;
        foreach ($this->getHooks() as $hook) {
            $result = $hook->$method($result, ...$params);
        }

        return $result;
    }

    /**
     * Gets the hook objects for the interface.
     *
     * @return list<Hook>
     */
    protected function getHooks(): array
    {
        $this->retrieveHooks();

        return $this->hookObjects;
    }

    /**
     * Retrieves the hook objects for the interface.
     *
     * @throws \UnexpectedValueException
     */
    protected function retrieveHooks(): void
    {
        if ($this->hooksHaveBeenRetrieved) {
            return;
        }

        $hookClasses = (array)($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][$this->index] ?? []);
        foreach ($hookClasses as $hookClass) {
            $hookInstance = GeneralUtility::makeInstance($hookClass);
            if (!($hookInstance instanceof $this->interfaceName)) {
                throw new \UnexpectedValueException(
                    'The class ' . \get_class($hookInstance) . ' is registered for the ' . $this->index .
                    ' hook list, but does not implement the ' . $this->interfaceName . ' interface.',
                    1448901897,
                );
            }
            $this->hookObjects[] = $hookInstance;
        }

        $this->hooksHaveBeenRetrieved = true;
    }

    /**
     * Validates the requested hooked-in methods.
     *
     * @param string $method the method to execute
     *
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    protected function validateHookMethod(string $method): void
    {
        if ($method === '') {
            throw new \InvalidArgumentException('The parameter $method must not be empty.', 1573479911);
        }
        if (!\in_array($method, \get_class_methods($this->interfaceName), true)) {
            throw new \UnexpectedValueException(
                'The interface ' . $this->interfaceName . ' does not contain method ' . $method . '.',
                1573480302,
            );
        }
    }
}
