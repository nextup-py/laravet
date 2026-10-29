<?php

namespace App\Filament\Pages;

use App\Support\UserGuide;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Guía de uso dentro del panel. La ve cualquier usuario del panel, pero solo
 * los temas de los recursos a los que su rol tiene acceso (ver UserGuide).
 */
class UserGuidePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Guía de uso';

    protected static ?string $title = 'Guía de uso';

    protected static ?string $slug = 'guia-de-uso';

    protected static ?string $navigationGroup = 'Ayuda';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.user-guide-page';

    #[Url(as: 'tema')]
    public ?string $topic = null;

    /**
     * @return Collection<string, array{slug: string, title: string, icon: string, order: int, permission: ?string, body: string}>
     */
    public function getTopics(): Collection
    {
        return UserGuide::topicsFor(auth()->user());
    }

    /**
     * @return array{slug: string, title: string, icon: string, order: int, permission: ?string, body: string}|null
     */
    public function getCurrentTopic(): ?array
    {
        $topics = $this->getTopics();

        return $topics->get($this->topic) ?? $topics->first();
    }

    public function getCurrentHtml(): string
    {
        $topic = $this->getCurrentTopic();

        return $topic ? UserGuide::toHtml($topic['body']) : '';
    }
}
