<?php

declare(strict_types=1);

namespace BoxInCoded\FilamentSiteKit\Core\Support;

class ExtensionsRegistry
{
    /**
     * @var array<int, class-string>
     */
    protected array $pages = [];

    /**
     * @var array<int, class-string>
     */
    protected array $widgets = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $navigationItems = [];

    /**
     * @param  array<int, class-string>  $pages
     */
    public function registerPages(array $pages): void
    {
        $this->pages = array_values(array_unique(array_merge($this->pages, $pages)));
    }

    /**
     * @param  array<int, class-string>  $widgets
     */
    public function registerWidgets(array $widgets): void
    {
        $this->widgets = array_values(array_unique(array_merge($this->widgets, $widgets)));
    }

    /**
     * @return array<int, class-string>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    /**
     * @return array<int, class-string>
     */
    public function widgets(): array
    {
        return $this->widgets;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function navigationItems(): array
    {
        return $this->navigationItems;
    }
}
