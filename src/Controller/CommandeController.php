<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * La création et la modification d'une commande sont dans DefaultController
 * (routes passe_commande_new et commande_edit).
 *
 * @Route("/commande")
 */
class CommandeController extends AbstractController
{
    /**
     * Récapitulatif de la dernière commande passée sur cet appareil (cookie « commande »).
     *
     * @Route("/", name="commande_index", methods={"GET"})
     */
    public function index(Request $request, CommandeRepository $commandeRepository): Response
    {
        $cookie = json_decode((string) $request->cookies->get('commande'));
        $commande = null;
        if (is_object($cookie) && isset($cookie->command_id)) {
            $commande = $commandeRepository->find((int) $cookie->command_id);
        }

        if (null === $commande) {
            $response = $this->redirectToRoute('passe_commande_index');
            if (is_object($cookie)) {
                // commande supprimée entre-temps (le cookie sert encore à pré-remplir le nom)
                $this->addFlash('info', 'Vous n’avez pas de commande en cours.');
            }

            return $response;
        }

        return $this->render('commande/index.html.twig', [
            'commande' => $commande,
        ]);
    }

    /**
     * @Route("/{id}", name="commande_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Commande $commande): Response
    {
        if ($this->isCsrfTokenValid('delete'.$commande->getId(), $request->request->get('_token'))) {
            $poidCommande = 0;
            foreach ($commande->getLigneCommandes() as $ligneCommande ){
                $poidCommande += $ligneCommande->getPain()->getPoid() * $ligneCommande->getQuantite();
            }

            $jourDistrib = $commande->getJourDistrib();
            $jourDistrib->setPoidRestant(round($jourDistrib->getPoidsCommande() - $poidCommande, 3));

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($commande);
            $entityManager->flush();

            $this->addFlash('success', sprintf('La commande de %s %s a été annulée.', $commande->getPrenom(), $commande->getNom()));
        }

        // On revient sur la page d'où l'on vient (synthèse, suivi…) si elle est connue
        $retour = $request->request->get('_retour');
        if ($retour && 0 === strpos($retour, '/') && 0 !== strpos($retour, '//')) {
            return $this->redirect($retour);
        }

        $response = $this->redirectToRoute('passe_commande_index');
        $response->headers->clearCookie('commande');

        return $response;
    }
}
