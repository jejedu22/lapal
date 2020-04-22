<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeType;
use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @Route("/commande")
 */
class CommandeController extends AbstractController
{
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @Route("/", name="commande_index", methods={"GET"})
     */
    public function index(CommandeRepository $commandeRepository): Response
    {
        $commandeId = $this->session->get('commande_id');

        if ($commandeId != null){
            return $this->render('commande/index.html.twig', [
                'commande' => $commandeRepository->findOneById($commandeId),
                ]);
        }
        else {
            return $this->redirectToRoute('passe_commande_index');
        }
    }

    /**
     * @Route("/new", name="commande_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
       dump( $this->session->get('commande_nom'));
       die;
        $commande = new Commande();
        $form = $this->createForm(CommandeType::class, $commande, [
            'lastNom' => $this->session->get('commande_nom'),
            'lastPrenom' => $this->session->get('commande_prenom'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($commande);
            $entityManager->flush();
            
            $this->session->set('commande_id', $commande->getId());
            $this->session->set('commande_nom', $commande->getNom());
            $this->session->set('commande_prenom', $commande->getPrenom());

            return $this->redirectToRoute('commande_index');
        }

        return $this->render('commande/new.html.twig', [
            'commande' => $commande,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}/edit", name="commande_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Commande $commande): Response
    {
        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('commande_index');
        }

        return $this->render('commande/edit.html.twig', [
            'commande' => $commande,
            'form' => $form->createView(),
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
            
            $poidRestant = $commande->getJourDistrib()->getPoidRestant();
            $poidRestant -= $poidCommande;

            $poidRestant = $commande->getJourDistrib()->setPoidRestant($poidRestant);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($commande);
            $entityManager->flush();
            $this->session->set('commande_id', null);
        }

        return $this->redirectToRoute('passe_commande_index');
    }
}
