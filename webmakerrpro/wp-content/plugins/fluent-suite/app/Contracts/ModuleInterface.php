<?php

namespace FluentSuite\Contracts;

interface ModuleInterface
{
    public function getSlug(): string;

    public function getName(): string;

    public function getDescription(): string;

    public function register(): void;

    public function activate(): void;

    public function deactivate(): void;
}
