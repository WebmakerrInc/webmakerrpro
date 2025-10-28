<?php

namespace FluentSuite\Modules;

use FluentSuite\Contracts\ModuleInterface;

abstract class Module implements ModuleInterface
{
    /**
     * @var string
     */
    protected $slug;

    public function __construct(string $slug)
    {
        $this->slug = $slug;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function activate(): void
    {
        // Silence is golden - individual modules can override as needed.
    }

    public function deactivate(): void
    {
        // Modules may override for cleanup tasks.
    }
}
