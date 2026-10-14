<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

/**
 * Renders action icons for row and batch action buttons.
 *
 * Icon sources:
 *   - plain text / emoji / ligature name  → `<span class="{class}" aria-hidden="true">{icon}</span>`
 *   - `ux:<name>`                         → rendered by Symfony UX Icons (explicit opt-in)
 *   - class only (icon null, class set)   → `<span class="{class}" aria-hidden="true"></span>`
 *
 * The class is tri-state: null inherits the global `kachnitel_admin.icons.class`,
 * '' (ICON_CLASS_NONE) means "no class" so the icon is output as bare escaped text.
 */
class AdminIconRuntime
{
    public const UX_PREFIX = 'ux:';

    /**
     * @param string|null $defaultIconClass Global `kachnitel_admin.icons.class`
     * @param object|null $uxIconRenderer   Symfony\UX\Icons\IconRendererInterface when symfony/ux-icons is installed
     */
    public function __construct(
        private readonly ?string $defaultIconClass = null,
        private readonly ?object $uxIconRenderer = null,
    ) {}

    /**
     * @return string HTML-safe markup, or '' when there is nothing to render
     */
    public function renderIcon(?string $icon, ?string $iconClass = null): string
    {
        $class = $iconClass ?? $this->defaultIconClass;
        $class = $class === null ? '' : trim($class);

        if ($icon !== null && str_starts_with($icon, self::UX_PREFIX)) {
            return $this->renderUxIcon(substr($icon, \strlen(self::UX_PREFIX)), $class);
        }

        if ($icon === null || $icon === '') {
            // Class-only icon fonts (e.g. Bootstrap Icons) need an explicitly declared class;
            // an inherited global class alone must never produce an empty icon.
            if ($iconClass === null || trim($iconClass) === '') {
                return '';
            }

            return $this->span($class, '');
        }

        if ($class === '') {
            return $this->escape($icon);
        }

        return $this->span($class, $this->escape($icon));
    }

    private function renderUxIcon(string $name, string $class): string
    {
        /**
         * @var null|callable(string, array<string, string>): string|null $callable
         */
        $callable = $this->uxIconRenderer === null ? null : [$this->uxIconRenderer, 'renderIcon'];

        if ($callable === null || !\is_callable($callable)) { // @phpstan-ignore function.alreadyNarrowedType
            throw new \LogicException(sprintf(
                'Icon "%s%s" requires symfony/ux-icons. Run "composer require symfony/ux-icons".',
                self::UX_PREFIX,
                $name,
            ));
        }

        $attributes = ['aria-hidden' => 'true'];
        if ($class !== '') {
            $attributes['class'] = $class;
        }

        return (string) $callable($name, $attributes);
    }

    private function span(string $class, string $content): string
    {
        return sprintf('<span class="%s" aria-hidden="true">%s</span>', $this->escape($class), $content);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
