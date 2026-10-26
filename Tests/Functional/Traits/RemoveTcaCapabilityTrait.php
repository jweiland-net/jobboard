<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Traits;

use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Removes optional capabilities (TCA ctrl) of a table, like an integrator
 * could do in a TCA override, and rebuilds the TCA schema. Call restoreTca()
 * in tearDown(). TcaSchemaFactory::rebuild() does not write the cache.
 */
trait RemoveTcaCapabilityTrait
{
    /**
     * @var array<string, mixed>
     */
    private array $originalTca = [];

    /**
     * TCA ctrl "enablecolumns" starttime and endtime
     */
    private function removeTimeRestrictions(string $table): void
    {
        $this->backupTca();
        unset(
            $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['starttime'],
            $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['endtime'],
        );
        $this->rebuildTcaSchema();
    }

    /**
     * TCA ctrl "languageField", "transOrigPointerField" and related
     */
    private function removeLanguageCapability(string $table): void
    {
        $this->backupTca();
        unset(
            $GLOBALS['TCA'][$table]['ctrl']['languageField'],
            $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'],
            $GLOBALS['TCA'][$table]['ctrl']['translationSource'],
            $GLOBALS['TCA'][$table]['ctrl']['transOrigDiffSourceField'],
        );
        $this->rebuildTcaSchema();
    }

    private function removeColumn(string $table, string $column): void
    {
        $this->backupTca();
        unset($GLOBALS['TCA'][$table]['columns'][$column]);
        $this->rebuildTcaSchema();
    }

    private function restoreTca(): void
    {
        if ($this->originalTca === []) {
            return;
        }

        $GLOBALS['TCA'] = $this->originalTca;
        $this->originalTca = [];
        $this->rebuildTcaSchema();
    }

    private function backupTca(): void
    {
        if ($this->originalTca === []) {
            $this->originalTca = $GLOBALS['TCA'];
        }
    }

    private function rebuildTcaSchema(): void
    {
        $this->get(TcaSchemaFactory::class)->rebuild($GLOBALS['TCA']);
    }
}
