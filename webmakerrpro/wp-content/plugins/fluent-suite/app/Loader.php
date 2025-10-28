<?php

namespace FluentSuite;

use FluentSuite\Contracts\ModuleInterface;

class Loader
{
    /**
     * @var array<string, array<string, string>>
     */
    protected $moduleMap = [];

    /**
     * @var array<string>
     */
    protected $activeModules = [];

    /**
     * @var array<string, ModuleInterface>
     */
    protected $instances = [];

    /**
     * @param array<string, array<string, string>> $moduleMap
     * @param array<string>                         $activeModules
     */
    public function __construct(array $moduleMap, array $activeModules)
    {
        $this->moduleMap      = $moduleMap;
        $this->activeModules  = $activeModules;
    }

    public function boot(): void
    {
        foreach ($this->activeModules as $slug) {
            $module = $this->resolve($slug);
            if ($module) {
                $module->activate();
                $module->register();
            }
        }
    }

    /**
     * @param string $slug
     *
     * @return ModuleInterface|null
     */
    public function resolve(string $slug): ?ModuleInterface
    {
        if (isset($this->instances[$slug])) {
            return $this->instances[$slug];
        }

        if (!isset($this->moduleMap[$slug])) {
            return null;
        }

        $class = $this->moduleMap[$slug]['class'] ?? '';
        if (!$class || !class_exists($class)) {
            return null;
        }

        $module = new $class($slug);
        if (!$module instanceof ModuleInterface) {
            return null;
        }

        $this->instances[$slug] = $module;

        return $module;
    }

    public function refresh(array $activeModules): void
    {
        $previousActive       = $this->activeModules;
        $this->activeModules  = $activeModules;

        $activated   = array_diff($activeModules, $previousActive);
        $deactivated = array_diff($previousActive, $activeModules);

        foreach ($activated as $slug) {
            $module = $this->resolve($slug);
            if ($module) {
                $module->activate();
                $module->register();
            }
        }

        foreach ($deactivated as $slug) {
            $module = $this->resolve($slug);
            if ($module) {
                $module->deactivate();
            }
        }
    }
}
