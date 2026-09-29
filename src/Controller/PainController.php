<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Pain;
use App\Form\PainType;
use App\Repository\PainRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/pain")
 */
class PainController extends AbstractController
{
    /**
     * @Route("/", name="pain_index", methods={"GET"})
     */
    public function index(PainRepository $painRepository): Response
    {
        return $this->render('pain/index.html.twig', [
            'pains' => $painRepository->findBy([], ['actif' => 'DESC', 'position' => 'ASC', 'nom' => 'ASC']),
        ]);
    }

    /**
     * Enregistre l'ordre d'affichage des pains (liste d'identifiants, du premier au dernier).
     *
     * @Route("/ordre", name="pain_ordre", methods={"POST"})
     */
    public function ordre(Request $request, PainRepository $painRepository): JsonResponse
    {
        if (!$this->isCsrfTokenValid('ordre-pains', $request->request->get('_token'))) {
            return new JsonResponse(['erreur' => 'Jeton invalide, rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $ids = array_map('intval', (array) $request->request->get('ordre', []));
        $pains = [];
        foreach ($painRepository->findBy(['id' => $ids]) as $pain) {
            $pains[$pain->getId()] = $pain;
        }
        $rang = 0;
        foreach ($ids as $id) {
            if (isset($pains[$id])) {
                $pains[$id]->setPosition(++$rang);
            }
        }
        $this->getDoctrine()->getManager()->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * @Route("/new", name="pain_new", methods={"GET","POST"})
     */
    public function new(Request $request, PainRepository $painRepository): Response
    {
        $pain = new Pain();
        // Un nouveau pain arrive en fin de liste
        $dernier = $painRepository->findOneBy([], ['position' => 'DESC']);
        $pain->setPosition($dernier ? $dernier->getPosition() + 1 : 1);
        $form = $this->createForm(PainType::class, $pain);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($pain);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Le pain « %s » a été ajouté.', $pain->getNom()));

            return $this->redirectToRoute('pain_index');
        }

        return $this->render('pain/new.html.twig', [
            'pain' => $pain,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}/edit", name="pain_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Pain $pain): Response
    {
        $form = $this->createForm(PainType::class, $pain);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', sprintf('Le pain « %s » a été modifié.', $pain->getNom()));

            return $this->redirectToRoute('pain_index');
        }

        $commandes = $this->commandesDuPain($pain);

        return $this->render('pain/edit.html.twig', [
            'pain' => $pain,
            'form' => $form->createView(),
            'nb_commandes' => count($commandes),
            'nb_commandes_a_venir' => count(array_filter($commandes, function (Commande $commande) {
                return 'passe' !== $commande->getJourDistrib()->getStatut();
            })),
        ]);
    }

    /**
     * @Route("/{id}", name="pain_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Pain $pain): Response
    {
        if ($this->isCsrfTokenValid('delete'.$pain->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();

            // Les commandes contenant ce pain sont supprimées entièrement (avec leurs autres pains) ;
            // le poids déjà commandé de leur jour est diminué d'autant
            $commandes = $this->commandesDuPain($pain);
            foreach ($commandes as $commande) {
                $poids = 0.0;
                foreach ($commande->getLigneCommandes() as $ligne) {
                    $poids += $ligne->getPain()->getPoid() * $ligne->getQuantite();
                }
                $jourDistrib = $commande->getJourDistrib();
                $jourDistrib->setPoidRestant(max(0.0, round($jourDistrib->getPoidsCommande() - $poids, 3)));
                $entityManager->remove($commande);
            }
            // Lignes isolées (sans commande) : supprimées aussi, sinon elles bloqueraient la suppression
            foreach ($pain->getLigneCommandes() as $ligne) {
                if (null === $ligne->getCommande()) {
                    $entityManager->remove($ligne);
                }
            }

            foreach ($pain->getJourDistribs()->toArray() as $jourDistrib) {
                $pain->removeJourDistrib($jourDistrib);
            }
            $entityManager->remove($pain);
            try {
                $entityManager->flush();
                $this->addFlash('success', count($commandes)
                    ? sprintf('Le pain « %s » a été supprimé, ainsi que %d commande%s qui le contenai%s.', $pain->getNom(), count($commandes), count($commandes) > 1 ? 's' : '', count($commandes) > 1 ? 'ent' : 't')
                    : sprintf('Le pain « %s » a été supprimé.', $pain->getNom()));
            } catch (ForeignKeyConstraintViolationException $e) {
                $this->addFlash('warning', 'Ce pain est encore utilisé : archivez-le plutôt.');
            }
        }

        return $this->redirectToRoute('pain_index');
    }

    /**
     * Commandes contenant ce pain (chacune une seule fois).
     *
     * @return Commande[]
     */
    private function commandesDuPain(Pain $pain): array
    {
        $commandes = [];
        foreach ($pain->getLigneCommandes() as $ligne) {
            $commande = $ligne->getCommande();
            if (null !== $commande) {
                $commandes[$commande->getId()] = $commande;
            }
        }

        return array_values($commandes);
    }
}
