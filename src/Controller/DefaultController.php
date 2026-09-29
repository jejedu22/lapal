<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\JourDistrib;
use App\Entity\LigneCommande;
use App\Form\CommandeType;
use App\Repository\CommandeRepository;
use App\Repository\JourDistribRepository;
use App\Service\OptionsSettings;
use App\Twig\AppExtension;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /** Quantité maximale d'un même pain dans une commande */
    const QUANTITE_MAX = 20;

    /**
     * @Route("/", name="passe_commande_index", methods={"GET"})
     */
    public function index(JourDistribRepository $jourDistribRepository): Response
    {
        return $this->render('passe_commande/index.html.twig', [
            'jour_distribs' => $jourDistribRepository->findAllActive(),
        ]);
    }

    /**
     * suivi = 0 : commandes à venir (public)
     * suivi = 1 : suivi des livraisons d'un jour
     * suivi = 2 : toutes les commandes, par date
     *
     * @Route("/synthese/{suivi}", name="synthese_index", methods={"GET"}, requirements={"suivi"="\d+"})
     */
    public function synthese(Request $request, JourDistribRepository $jourDistribRepository, int $suivi): Response
    {
        if (1 === $suivi) {
            return $this->suiviLivraison($request, $jourDistribRepository);
        }

        $jourDistribs = 0 === $suivi
            ? $jourDistribRepository->findAllActive()
            : $jourDistribRepository->findAllOrder('DESC', 100);

        return $this->render('passe_commande/synthese.html.twig', [
            'jour_distribs' => $jourDistribs,
            'suivi' => $suivi,
        ]);
    }

    private function suiviLivraison(Request $request, JourDistribRepository $jourDistribRepository): Response
    {
        $jours = $jourDistribRepository->findAllOrder('DESC', 100);

        // Jour affiché : celui demandé, sinon aujourd'hui, sinon la prochaine distribution, sinon la dernière
        $jour = null;
        $idDemande = $request->query->getInt('jour');
        foreach ($jours as $candidat) {
            if ($idDemande === $candidat->getId()) {
                $jour = $candidat;
            }
        }
        if (null === $jour) {
            $aujourdhui = date('Y-m-d');
            foreach ($jours as $candidat) {
                // les jours sont triés du plus récent au plus ancien
                if ($candidat->getDate()->format('Y-m-d') >= $aujourdhui) {
                    $jour = $candidat;
                }
            }
            $jour = $jour ?? ($jours[0] ?? null);
        }

        $commandes = [];
        if (null !== $jour) {
            $commandes = $jour->getCommandes()->toArray();
            usort($commandes, function (Commande $a, Commande $b) {
                return strcasecmp($a->getNom() . ' ' . $a->getPrenom(), $b->getNom() . ' ' . $b->getPrenom());
            });
        }

        return $this->render('passe_commande/suivi.html.twig', [
            'jours' => $jours,
            'jour' => $jour,
            'commandes' => $commandes,
            'suivi' => 1,
        ]);
    }

    /**
     * Conservé pour les anciens liens : marque la commande livrée puis revient au suivi.
     *
     * @Route("/livree/{commandeId}", name="livree_commande", methods={"GET"})
     */
    public function livreeCommande(CommandeRepository $commandeRepository, int $commandeId): Response
    {
        $commande = $commandeRepository->find($commandeId);
        if (null === $commande) {
            throw $this->createNotFoundException('Commande introuvable');
        }
        $commande->setLivree(true);
        $this->getDoctrine()->getManager()->flush();

        return $this->redirectToRoute('synthese_index', [
            'suivi' => 1,
            'jour' => $commande->getJourDistrib() ? $commande->getJourDistrib()->getId() : null,
        ]);
    }

    /**
     * Coche / décoche « livrée » depuis l'écran de suivi, sans recharger la page.
     *
     * @Route("/livree/{id}/basculer", name="livree_basculer", methods={"POST"})
     */
    public function basculerLivree(Request $request, Commande $commande): JsonResponse
    {
        if (!$this->isCsrfTokenValid('livree', $request->request->get('_token'))) {
            return new JsonResponse(['erreur' => 'Jeton invalide, rechargez la page.'], 400);
        }

        $commande->setLivree('1' === $request->request->get('livree'));
        $this->getDoctrine()->getManager()->flush();

        return new JsonResponse(['id' => $commande->getId(), 'livree' => $commande->getLivree()]);
    }

    /**
     * @Route("/synthesepoids", name="synthese_poids", methods={"GET"})
     */
    public function synthesePoids(JourDistribRepository $jourDistribRepository): Response
    {
        $jours = $jourDistribRepository->findAllOrder('DESC');
        $ids = array_map(function (JourDistrib $jour) { return $jour->getId(); }, $jours);

        return $this->render('passe_commande/synthese_poids.html.twig', [
            'jours' => $jours,
            'quantites' => $jourDistribRepository->findQuantitesParPain($ids),
        ]);
    }

    /**
     * @Route("/new/{idJourDistrib}", name="passe_commande_new", methods={"GET","POST"})
     */
    public function new(Request $request, int $idJourDistrib, JourDistribRepository $jourDistribRepository): Response
    {
        $jourDistrib = $jourDistribRepository->find($idJourDistrib);
        if (null === $jourDistrib) {
            throw $this->createNotFoundException('Jour de distribution introuvable');
        }
        if (!$jourDistrib->isOuvert()) {
            $this->addFlash('warning', $this->messageFerme($jourDistrib));

            return $this->redirectToRoute('passe_commande_index');
        }

        $commande = new Commande();
        $commande->setJourDistrib($jourDistrib);
        $commande->setLivree(false);

        // On pré-remplit avec le nom de la dernière commande passée sur cet appareil
        $cookie = json_decode((string) $request->cookies->get('commande'));
        if (is_object($cookie)) {
            $commande->setNom((string) ($cookie->nom ?? ''));
            $commande->setPrenom((string) ($cookie->prenom ?? ''));
        }

        return $this->formulaireCommande($request, $commande, true);
    }

    /**
     * @Route("/commande/{id}/edit", name="commande_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Commande $commande): Response
    {
        $jourDistrib = $commande->getJourDistrib();
        if (null === $jourDistrib || !in_array($jourDistrib->getStatut(), ['ouvert', 'complet'], true)) {
            $this->addFlash('warning', 'Cette commande ne peut plus être modifiée : ' . lcfirst($this->messageFerme($jourDistrib)));

            return $this->redirectToRoute('commande_index');
        }

        return $this->formulaireCommande($request, $commande, false);
    }

    /**
     * Formulaire commun à la création et à la modification d'une commande.
     */
    private function formulaireCommande(Request $request, Commande $commande, bool $creation): Response
    {
        $jourDistrib = $commande->getJourDistrib();

        // Quantités actuelles de la commande (modification) indexées par pain
        $quantitesActuelles = [];
        $poidsActuel = 0.0;
        foreach ($commande->getLigneCommandes() as $ligne) {
            $idPain = $ligne->getPain()->getId();
            $quantitesActuelles[$idPain] = ($quantitesActuelles[$idPain] ?? 0) + $ligne->getQuantite();
            $poidsActuel += $ligne->getPain()->getPoid() * $ligne->getQuantite();
        }

        // Pains proposés : ceux du jour + ceux déjà présents dans la commande
        $pains = [];
        foreach ($jourDistrib->getPains() as $pain) {
            $pains[$pain->getId()] = $pain;
        }
        foreach ($commande->getLigneCommandes() as $ligne) {
            $pains[$ligne->getPain()->getId()] = $ligne->getPain();
        }
        $collator = class_exists(\Collator::class) ? new \Collator('fr_FR') : null;
        uasort($pains, function ($a, $b) use ($collator) {
            return $collator ? $collator->compare($a->getNom(), $b->getNom()) : strcasecmp($a->getNom(), $b->getNom());
        });

        // Poids que cette commande peut atteindre
        $disponible = round($jourDistrib->getPoidsDisponible() + $poidsActuel, 3);

        $form = $this->createForm(CommandeType::class, $commande, ['client' => true]);
        $form->handleRequest($request);

        $quantites = $quantitesActuelles;
        if ($form->isSubmitted()) {
            $saisie = $request->request->get('quantites', []);
            $quantites = [];
            foreach ($pains as $idPain => $pain) {
                $q = isset($saisie[$idPain]) ? (int) $saisie[$idPain] : 0;
                $quantites[$idPain] = max(0, min(self::QUANTITE_MAX, $q));
            }

            $poidsCommande = 0.0;
            foreach ($quantites as $idPain => $q) {
                $poidsCommande += $pains[$idPain]->getPoid() * $q;
            }
            $poidsCommande = round($poidsCommande, 3);

            if (0 === array_sum($quantites)) {
                $form->addError(new FormError('Choisissez au moins un pain.'));
            } elseif ($poidsCommande > $disponible) {
                $form->addError(new FormError(sprintf(
                    'Il ne reste que %s pour ce jour et votre commande pèse %s. Retirez quelques pains.',
                    AppExtension::kg($disponible),
                    AppExtension::kg($poidsCommande)
                )));
            }

            if ($form->isValid()) {
                $entityManager = $this->getDoctrine()->getManager();

                // On remplace les lignes existantes par les nouvelles quantités
                foreach ($commande->getLigneCommandes()->toArray() as $ligne) {
                    $commande->removeLigneCommande($ligne);
                    $entityManager->remove($ligne);
                }
                foreach ($quantites as $idPain => $q) {
                    if ($q > 0) {
                        $ligne = new LigneCommande();
                        $ligne->setPain($pains[$idPain]);
                        $ligne->setQuantite($q);
                        $commande->addLigneCommande($ligne);
                    }
                }

                // poidRestant = poids total déjà commandé pour ce jour
                $jourDistrib->setPoidRestant(round($jourDistrib->getPoidsCommande() - $poidsActuel + $poidsCommande, 3));

                $entityManager->persist($commande);
                $entityManager->flush();

                $response = $this->redirectToRoute('commande_index');
                $response->headers->setCookie(new Cookie('commande', json_encode([
                    'command_id' => $commande->getId(),
                    'nom' => $commande->getNom(),
                    'prenom' => $commande->getPrenom(),
                ]), time() + (2 * 365 * 24 * 60 * 60)));

                $this->addFlash('success', $creation ? 'Votre commande a bien été enregistrée.' : 'Votre commande a été modifiée.');

                return $response;
            }
        }

        return $this->render('commande/new.html.twig', [
            'commande' => $commande,
            'jour_distrib' => $jourDistrib,
            'form' => $form->createView(),
            'pains' => $pains,
            'quantites' => $quantites,
            'disponible' => $disponible,
            'quantite_max' => self::QUANTITE_MAX,
            'creation' => $creation,
        ]);
    }

    private function messageFerme(?JourDistrib $jourDistrib): string
    {
        switch ($jourDistrib ? $jourDistrib->getStatut() : 'passe') {
            case 'complet':
                return 'Cette distribution est complète.';
            case 'ferme':
                return 'Les commandes sont fermées pour cette distribution.';
            case 'aujourdhui':
                return 'Les commandes sont closes : la distribution a lieu aujourd’hui.';
            default:
                return 'Cette distribution est terminée.';
        }
    }

    /**
     * Manifeste pour « Ajouter à l'écran d'accueil » sur téléphone.
     *
     * @Route("/manifest.webmanifest", name="app_manifest", methods={"GET"})
     */
    public function manifest(OptionsSettings $options): JsonResponse
    {
        $nom = $options->get('name', 'Lapal');
        $couleur = AppExtension::couleurHex($options->get('color', 'orange'));
        $manifest = [
            'name' => $nom,
            'short_name' => $nom,
            'start_url' => $this->generateUrl('passe_commande_index'),
            'display' => 'standalone',
            'background_color' => '#f4f6f9',
            'theme_color' => $couleur,
            'lang' => 'fr',
        ];
        if ($logo = $options->get('logo')) {
            $manifest['icons'] = [
                ['src' => '/uploads/logo/' . $logo, 'sizes' => 'any', 'type' => 'image/png', 'purpose' => 'any'],
            ];
        }

        $response = new JsonResponse($manifest);
        $response->headers->set('Content-Type', 'application/manifest+json');

        return $response;
    }
}
