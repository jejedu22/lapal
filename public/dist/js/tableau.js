/*
 * Tableaux interactifs (remplace DataTables) : recherche, tri, pagination.
 * Aucune dépendance.
 *
 * Classe tableau-cartes : sur petit écran, chaque ligne devient une carte
 * (les libellés des colonnes sont recopiés dans data-label, voir style.css).
 * Attribut data-tableau : ajoute la recherche, le tri et la pagination.
 *
 * Attributs de la table :
 *   data-tableau                 active le composant
 *   data-par-page="25"           taille de page (0 ou absent : pas de pagination)
 *   data-tri="0" / "0:desc"      colonne triée au chargement
 *   data-recherche="true|false"  force l'affichage du champ de recherche
 *                                (par défaut : affiché au-delà de 5 lignes)
 *   data-nom="commande"          libellé des éléments (« 12 commandes »)
 * Attributs des cellules :
 *   th[data-tri="false"]         colonne non triable
 *   td[data-order]               valeur utilisée pour le tri (sinon le texte)
 *   td[data-label=""]            cellule sans libellé en mode carte (actions)
 *
 * Une ligne d'une seule cellule fusionnée (« Aucun élément… ») est ignorée.
 */
(function () {
    'use strict';

    var comparateur = new Intl.Collator('fr', { numeric: true, sensitivity: 'base' });

    function normaliser(texte) {
        return texte.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
    }

    function valeurTri(cellule) {
        if (!cellule) return '';
        return cellule.hasAttribute('data-order') ? cellule.getAttribute('data-order') : cellule.textContent.trim();
    }

    function comparer(a, b) {
        var na = parseFloat(a), nb = parseFloat(b);
        if (a !== '' && b !== '' && !isNaN(na) && !isNaN(nb) && isFinite(a) && isFinite(b)) {
            return na - nb;
        }
        return comparateur.compare(a, b);
    }

    function estVide(ligne) {
        return ligne.cells.length === 1 && ligne.cells[0].colSpan > 1;
    }

    // Recopie le libellé de chaque colonne sur ses cellules (corps et pied)
    function etiqueter(table) {
        if (!table.tHead || !table.tHead.rows.length) return;
        var libelles = Array.prototype.map.call(table.tHead.rows[0].cells, function (th) {
            return th.textContent.trim();
        });
        var lignes = Array.prototype.slice.call(table.tBodies).reduce(function (tout, corps) {
            return tout.concat(Array.prototype.slice.call(corps.rows));
        }, []);
        if (table.tFoot) lignes = lignes.concat(Array.prototype.slice.call(table.tFoot.rows));
        lignes.forEach(function (ligne) {
            if (estVide(ligne)) return;
            Array.prototype.forEach.call(ligne.cells, function (td, i) {
                if (libelles[i] && !td.hasAttribute('data-label')) td.setAttribute('data-label', libelles[i]);
            });
        });
    }

    function pluriel(n, nom) {
        return n + ' ' + nom + (n > 1 ? 's' : '');
    }

    function Tableau(table) {
        this.table = table;
        this.corps = table.tBodies[0];
        this.entetes = Array.prototype.slice.call(table.tHead.rows[0].cells);
        this.lignes = Array.prototype.slice.call(this.corps.rows).filter(function (ligne) {
            return !estVide(ligne);
        });
        this.parPage = parseInt(table.getAttribute('data-par-page'), 10) || 0;
        this.nom = table.getAttribute('data-nom') || 'ligne';
        this.page = 0;
        this.filtre = '';
        this.tri = null;

        // Texte indexé pour la recherche, calculé une seule fois
        this.lignes.forEach(function (ligne) {
            ligne._texte = normaliser(ligne.textContent);
        });

        this.construireBarre();
        this.construireTri();

        var triInitial = table.getAttribute('data-tri');
        if (triInitial !== null && triInitial !== '') {
            var morceaux = triInitial.split(':');
            this.trier(parseInt(morceaux[0], 10), morceaux[1] === 'desc' ? 'descending' : 'ascending');
        } else {
            this.afficher();
        }
    }

    Tableau.prototype.construireBarre = function () {
        var self = this;
        var barre = this.barre = document.createElement('div');
        barre.className = 'tableau-barre';

        var recherche = this.table.getAttribute('data-recherche');
        if (recherche === null ? this.lignes.length > 5 : recherche !== 'false') {
            var id = (this.table.id || 'tableau') + '-recherche';
            barre.innerHTML =
                '<label class="sr-only" for="' + id + '">Rechercher</label>' +
                '<div class="input-group input-group-sm tableau-recherche">' +
                    '<div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span></div>' +
                    '<input type="search" class="form-control" id="' + id + '" placeholder="Rechercher…" autocomplete="off">' +
                '</div>';
            var champ = this.champ = barre.querySelector('input');
            champ.addEventListener('input', function () {
                self.filtre = normaliser(champ.value);
                self.page = 0;
                self.afficher();
            });
        }

        this.info = document.createElement('div');
        this.info.className = 'tableau-info text-muted small';
        this.info.setAttribute('aria-live', 'polite');
        barre.appendChild(this.info);
        this.table.parentNode.insertBefore(barre, this.table);

        this.vide = document.createElement('p');
        this.vide.className = 'tableau-vide text-muted';
        this.vide.textContent = 'Aucun résultat.';
        this.vide.hidden = true;
        this.table.parentNode.insertBefore(this.vide, this.table.nextSibling);

        this.pagination = document.createElement('nav');
        this.pagination.className = 'tableau-pagination';
        this.pagination.setAttribute('aria-label', 'Pagination');
        this.vide.parentNode.insertBefore(this.pagination, this.vide.nextSibling);
        this.pagination.addEventListener('click', function (e) {
            var bouton = e.target.closest('button[data-page]');
            if (!bouton || bouton.disabled) return;
            self.page = parseInt(bouton.getAttribute('data-page'), 10);
            self.afficher();
            self.table.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    };

    Tableau.prototype.construireTri = function () {
        var self = this;
        this.entetes.forEach(function (th, i) {
            if (th.getAttribute('data-tri') === 'false' || !th.textContent.trim()) return;
            var bouton = document.createElement('button');
            bouton.type = 'button';
            bouton.className = 'tableau-tri';
            while (th.firstChild) bouton.appendChild(th.firstChild);
            th.appendChild(bouton);
            th.setAttribute('aria-sort', 'none');
            bouton.addEventListener('click', function () {
                var sens = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
                self.trier(i, sens);
            });
        });
    };

    Tableau.prototype.trier = function (colonne, sens) {
        var facteur = sens === 'descending' ? -1 : 1;
        this.entetes.forEach(function (th, i) {
            if (th.hasAttribute('aria-sort')) th.setAttribute('aria-sort', i === colonne ? sens : 'none');
        });
        this.lignes.sort(function (a, b) {
            return facteur * comparer(valeurTri(a.cells[colonne]), valeurTri(b.cells[colonne]));
        });
        var fragment = document.createDocumentFragment();
        this.lignes.forEach(function (ligne) { fragment.appendChild(ligne); });
        this.corps.appendChild(fragment);
        this.page = 0;
        this.afficher();
    };

    Tableau.prototype.afficher = function () {
        var filtre = this.filtre;
        var visibles = this.lignes.filter(function (ligne) {
            return !filtre || ligne._texte.indexOf(filtre) !== -1;
        });
        var total = visibles.length;
        var nbPages = this.parPage ? Math.max(1, Math.ceil(total / this.parPage)) : 1;
        if (this.page >= nbPages) this.page = nbPages - 1;
        var debut = this.parPage ? this.page * this.parPage : 0;
        var fin = this.parPage ? debut + this.parPage : total;

        this.lignes.forEach(function (ligne) { ligne.hidden = true; });
        visibles.slice(debut, fin).forEach(function (ligne) { ligne.hidden = false; });

        this.vide.hidden = total > 0;
        this.table.tHead.hidden = total === 0;

        var texte = pluriel(total, this.nom);
        if (filtre) texte += ' sur ' + this.lignes.length;
        if (nbPages > 1) texte = (debut + 1) + '–' + Math.min(fin, total) + ' sur ' + texte;
        this.info.textContent = texte;
        // Sans recherche ni pagination, la barre n'apporte rien
        this.barre.hidden = !this.champ && nbPages <= 1;

        this.afficherPagination(nbPages);
    };

    Tableau.prototype.afficherPagination = function (nbPages) {
        this.pagination.hidden = nbPages <= 1;
        if (nbPages <= 1) { this.pagination.innerHTML = ''; return; }
        var page = this.page;
        var html = '<ul class="pagination pagination-sm mb-0">';
        function item(cible, libelle, etat, aria) {
            html += '<li class="page-item' + (etat ? ' ' + etat : '') + '">' +
                '<button type="button" class="page-link" data-page="' + cible + '"' +
                (etat === 'disabled' ? ' disabled' : '') +
                (etat === 'active' ? ' aria-current="page"' : '') +
                (aria ? ' aria-label="' + aria + '"' : '') + '>' + libelle + '</button></li>';
        }
        item(page - 1, '‹', page === 0 ? 'disabled' : '', 'Page précédente');
        for (var p = 0; p < nbPages; p++) {
            // Première, dernière et voisines de la page courante ; « … » ailleurs
            if (p === 0 || p === nbPages - 1 || Math.abs(p - page) <= 1) {
                item(p, p + 1, p === page ? 'active' : '', 'Page ' + (p + 1));
            } else if (Math.abs(p - page) === 2) {
                html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }
        item(page + 1, '›', page === nbPages - 1 ? 'disabled' : '', 'Page suivante');
        this.pagination.innerHTML = html + '</ul>';
    };

    function initialiser() {
        document.querySelectorAll('table.tableau-cartes, table[data-tableau]').forEach(function (table) {
            if (table._tableau) return;
            etiqueter(table);
            table._tableau = true;
            if (!table.hasAttribute('data-tableau') || !table.tHead || !table.tBodies.length) return;
            // Tableau vide (ligne « Aucun… ») : rien à trier ni à rechercher
            var pleines = Array.prototype.filter.call(table.tBodies[0].rows, function (ligne) { return !estVide(ligne); });
            if (pleines.length) table._tableau = new Tableau(table);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiser);
    } else {
        initialiser();
    }
})();
