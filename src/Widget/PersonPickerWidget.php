<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Widget;

use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsException;
use SamuelHeer\ContaoChurchToolsBundle\Person\PersonProvider;

/**
 * Personenauswahl per Namenssuche statt per ID.
 *
 * Gespeichert wird weiterhin die ChurchTools-Personen-ID - im Backend tippt man
 * aber einen Namen und wählt aus den Treffern. Die Suche läuft live gegen die
 * zentrale ChurchTools-Suche derselben Instanz, die für diese Website gilt.
 */
class PersonPickerWidget extends Widget
{
    protected $blnSubmitInput = true;
    protected $strTemplate = 'be_widget';

    public function generate(): string
    {
        $id = $this->strId;
        $value = (int) $this->varValue;
        $label = $value > 0 ? $this->resolveName($value) : '';
        $routeUrl = System::getContainer()->get('router')->generate('contao_churchtools_person_search');
        $searchUrl = $routeUrl.'?id='.$this->currentElementId().'&q=';

        return \sprintf(
            <<<'HTML'
                <div class="ct-person-picker" data-url="%s">
                    <input type="hidden" name="%s" id="ctrl_%s" value="%s">
                    <input type="text" class="tl_text" id="ctrl_%s_search" value="%s"
                           placeholder="Namen eingeben, mindestens zwei Zeichen …" autocomplete="off" style="width:100%%">
                    <div id="ctrl_%s_hint" class="tl_gray" style="margin-top:.3rem">%s</div>
                    <ul id="ctrl_%s_results" style="display:none;list-style:none;margin:.3rem 0 0;padding:0;border:1px solid #ddd;max-height:16rem;overflow:auto;background:#fff"></ul>
                </div>
                <script>
                (function () {
                    var wrap = document.currentScript.previousElementSibling;
                    var hidden = document.getElementById('ctrl_%s');
                    var search = document.getElementById('ctrl_%s_search');
                    var list = document.getElementById('ctrl_%s_results');
                    var hint = document.getElementById('ctrl_%s_hint');
                    var timer = null;

                    function clear() { list.innerHTML = ''; list.style.display = 'none'; }

                    function choose(item) {
                        hidden.value = item.id;
                        search.value = item.name;
                        hint.textContent = item.released
                            ? 'ChurchTools-ID ' + item.id + ' - freigegeben'
                            : 'ChurchTools-ID ' + item.id + ' - ACHTUNG: nicht in der Freigabegruppe, erscheint nicht auf der Website';
                        clear();
                    }

                    function render(data) {
                        clear();
                        if (data.error) { hint.textContent = data.error; return; }
                        if (!data.results.length) { hint.textContent = 'Keine Treffer.'; return; }
                        data.results.forEach(function (item) {
                            var li = document.createElement('li');
                            li.style.cssText = 'padding:.35rem .5rem;cursor:pointer;border-bottom:1px solid #f0f0f0';
                            li.textContent = item.name + (item.released ? '' : '  (nicht freigegeben)');
                            if (!item.released) { li.style.color = '#a00'; }
                            li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(item); });
                            list.appendChild(li);
                        });
                        list.style.display = 'block';
                    }

                    search.addEventListener('input', function () {
                        window.clearTimeout(timer);
                        var q = search.value.trim();
                        if (q.length < 2) { clear(); return; }
                        // Kurz warten, damit nicht jeder Tastendruck eine Anfrage ausloest.
                        timer = window.setTimeout(function () {
                            hint.textContent = 'Suche …';
                            fetch(wrap.dataset.url + encodeURIComponent(q), {credentials: 'same-origin'})
                                .then(function (r) { return r.json(); })
                                .then(render)
                                .catch(function () { hint.textContent = 'Suche nicht erreichbar.'; });
                        }, 300);
                    });

                    search.addEventListener('blur', function () { window.setTimeout(clear, 150); });
                })();
                </script>
                HTML,
            StringUtil::specialchars($searchUrl),
            $this->strName,
            $id,
            $value ?: '',
            $id,
            StringUtil::specialchars($label),
            $id,
            StringUtil::specialchars($value > 0 ? 'ChurchTools-ID '.$value : 'Noch keine Person gewählt.'),
            $id,
            $id,
            $id,
            $id,
            $id,
        );
    }

    /**
     * ID des bearbeiteten Inhaltselements. Daraus leitet sich ab, welche
     * ChurchTools-Instanz gilt - die der zugehörigen Startseite.
     */
    private function currentElementId(): int
    {
        $fromDca = (int) ($this->dataContainer?->id ?? 0);

        return $fromDca > 0 ? $fromDca : (int) Input::get('id');
    }

    /**
     * Name der gespeicherten Person, damit im Feld nicht nur eine Zahl steht.
     */
    private function resolveName(int $personId): string
    {
        $container = System::getContainer();
        $registry = $container->get(ConnectionRegistry::class);
        $connection = $registry->forContentElement($this->currentElementId());

        if (null === $connection) {
            $all = $registry->all();
            $connection = 1 === \count($all) ? reset($all) : null;
        }

        if (null === $connection) {
            return '';
        }

        try {
            $person = $container->get(PersonProvider::class)->get($connection, $personId);
        } catch (ChurchToolsException) {
            return '';
        }

        return $person?->fullName() ?? '';
    }
}
