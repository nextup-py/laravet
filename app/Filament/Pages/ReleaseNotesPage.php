<?php

namespace App\Filament\Pages;

use App\Support\ReleaseNotes;
use App\Support\UserGuide;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Novedades del sistema (ver ReleaseNotes). La ve cualquier usuario del panel.
 */
class ReleaseNotesPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Novedades';

    protected static ?string $title = 'Novedades del sistema';

    protected static ?string $slug = 'novedades';

    protected static ?string $navigationGroup = 'Ayuda';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.release-notes-page';

    /**
     * @return Collection<string, array{slug: string, title: string, date: Carbon, body: string, html: string}>
     */
    public function getEntries(): Collection
    {
        return ReleaseNotes::all()->map(fn (array $entry): array => [...$entry, 'html' => UserGuide::toHtml($entry['body'])]);
    }
}
