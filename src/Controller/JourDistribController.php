<?php

namespace App\Controller;

use App\Entity\JourDistrib;
use App\Form\JourDistribType;
use App\Repository\JourDistribRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jour/distrib")
 */
class JourDistribController extends AbstractController
{
    /**
     * @Route("/", name="jour_distrib_index", methods={"GET"})
     */
    public function index(JourDistribRepository $jourDistribRepository): Response
    {
        return $this->render('jour_distrib/index.html.twig', [
            'jour_distribs' => $jourDistribRepository->findBy([],['date' => 'DESC']),
        ]);
    }

    /**
     * @Route("/new", name="jour_distrib_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $jourDistrib = new JourDistrib();
        $form = $this->createForm(JourDistribType::class, $jourDistrib);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($jourDistrib);
            $entityManager->flush();
            $this->addFlash('success', 'Le jour de distribution a été créé.');

            return $this->redirectToRoute('jour_distrib_index');
        }

        return $this->render('jour_distrib/new.html.twig', [
            'jour_distrib' => $jourDistrib,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}/edit", name="jour_distrib_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, JourDistrib $jourDistrib): Response
    {
        $form = $this->createForm(JourDistribType::class, $jourDistrib, [
            'edit' => true, 
            ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'Le jour de distribution a été modifié.');

            return $this->redirectToRoute('jour_distrib_index');
        }

        return $this->render('jour_distrib/edit.html.twig', [
            'jour_distrib' => $jourDistrib,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="jour_distrib_delete", methods={"DELETE"})
     */
    public function delete(Request $request, JourDistrib $jourDistrib): Response
    {
        if ($this->isCsrfTokenValid('delete'.$jourDistrib->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($jourDistrib);
            $entityManager->flush();
            $this->addFlash('success', 'Le jour de distribution a été supprimé.');
        }

        return $this->redirectToRoute('jour_distrib_index');
    }

    /**
     * Ouvre ou ferme les commandes d'un jour depuis la liste.
     *
     * @Route("/{id}/fermeture", name="jour_distrib_fermeture", methods={"POST"})
     */
    public function basculerFermeture(Request $request, JourDistrib $jourDistrib): Response
    {
        if ($this->isCsrfTokenValid('fermeture'.$jourDistrib->getId(), $request->request->get('_token'))) {
            $jourDistrib->setClosed(!$jourDistrib->getClosed());
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', $jourDistrib->getClosed() ? 'Les commandes sont fermées pour ce jour.' : 'Les commandes sont rouvertes pour ce jour.');
        }

        return $this->redirectToRoute('jour_distrib_index');
    }

    /**
     * Bon de préparation imprimable : quantités par pain + liste des commandes.
     *
     * @Route("/{id}/bon", name="jour_distrib_bon", methods={"GET"})
     */
    public function bon(JourDistrib $jourDistrib, JourDistribRepository $jourDistribRepository): Response
    {
        return $this->render('jour_distrib/bon.html.twig', [
            'jour' => $jourDistrib,
            'lignes' => $jourDistribRepository->findQuantitesParPain([$jourDistrib->getId()])[$jourDistrib->getId()] ?? [],
            'commandes' => $this->commandesTriees($jourDistrib),
        ]);
    }

    /**
     * Export CSV (séparateur « ; », UTF-8 avec BOM pour Excel) : une ligne par pain commandé.
     *
     * @Route("/{id}/export.csv", name="jour_distrib_export", methods={"GET"})
     */
    public function export(JourDistrib $jourDistrib): Response
    {
        $commandes = $this->commandesTriees($jourDistrib);

        $response = new StreamedResponse(function () use ($commandes) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['Nom', 'Prénom', 'Pain', 'Poids unitaire (kg)', 'Quantité', 'Prix unitaire (€)', 'Montant (€)', 'Livrée', 'Commentaire'], ';');
            foreach ($commandes as $commande) {
                foreach ($commande->getLigneCommandes() as $ligne) {
                    $pain = $ligne->getPain();
                    fputcsv($sortie, [
                        $commande->getNom(),
                        $commande->getPrenom(),
                        $pain->getNom(),
                        str_replace('.', ',', (string) $pain->getPoid()),
                        $ligne->getQuantite(),
                        number_format($pain->getPrix(), 2, ',', ''),
                        number_format($pain->getPrix() * $ligne->getQuantite(), 2, ',', ''),
                        $commande->getLivree() ? 'oui' : 'non',
                        (string) $commande->getCommentaire(),
                    ], ';');
                }
            }
            fclose($sortie);
        });

        $nomFichier = sprintf('commandes-%s.csv', $jourDistrib->getDate()->format('Y-m-d'));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $nomFichier));

        return $response;
    }

    private function commandesTriees(JourDistrib $jourDistrib): array
    {
        $commandes = $jourDistrib->getCommandes()->toArray();
        usort($commandes, function ($a, $b) {
            return strcasecmp($a->getNom() . ' ' . $a->getPrenom(), $b->getNom() . ' ' . $b->getPrenom());
        });

        return $commandes;
    }
}
