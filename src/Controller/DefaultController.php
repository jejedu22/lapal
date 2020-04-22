<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeType;
use App\Entity\JourDistrib;

use App\Repository\CommandeRepository;
use App\Repository\JourDistribRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="passe_commande_index", methods={"GET"})
     */
    public function index(JourDistribRepository $jourDistribRepository): Response
    {
        return $this->render('passe_commande/index.html.twig', [
            'jour_distribs' => $jourDistribRepository->findAll(),
            'poid_restant' => $jourDistribRepository->findPoid(),
        ]);
    }

    /**
     * @Route("/synthese", name="synthese_index", methods={"GET"})
     */
    public function synthese(JourDistribRepository $jourDistribRepository): Response
    {
        return $this->render('passe_commande/synthese.html.twig', [
            'jour_distribs' => $jourDistribRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new/{idJourDistrib}", name="passe_commande_new", methods={"GET","POST"})
     */
    public function new(Request $request, int $idJourDistrib, JourDistribRepository $jourDistribRepository): Response
    {
        $commande = new Commande();
        $jourDistrib = $jourDistribRepository->findOneById($idJourDistrib);
        $pains = $jourDistrib->getPains();

        $form = $this->createForm(CommandeType::class, $commande, ['pains' => $pains, 'idJourDistrib' => $idJourDistrib, 'jourDistrib' => $jourDistrib]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {

            // On récupère la somme des poids des pain de la commande
            $poidCommande = 0;
            foreach ($form->getData()->getLigneCommandes() as $ligneCommande ){
                $poidCommande += $ligneCommande->getPain()->getPoid() * floatval($ligneCommande->getQuantite());
            }

            // On additionne avec le poid restant du jour
            $poidRestant = $form->getData()->getJourDistrib()->getPoidRestant();
            $poidRestant += $poidCommande;

            if ($poidRestant <= $form->getData()->getJourDistrib()->getTotal()) {

                $form->getData()->getJourDistrib()->setPoidRestant($poidRestant);
                dump($form->getData()->getJourDistrib()->getPoidRestant());
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($commande);
                $entityManager->flush();
                
                $this->addFlash(
                    'success',
                    'La commande a été enregistrée'
                    
                );            
                return $this->redirectToRoute('commande_index');
            }
            else {
                $this->addFlash(
                    'warning',
                    'La limte de poid disponible a été dépassée !'
                );
                return $this->redirectToRoute('passe_commande_index');
            }
            
        }

        return $this->render('commande/new.html.twig', [
            'commande' => $commande,
            'form' => $form->createView(),
            'pains' => $pains,
            'idJourDistrib' => $jourDistrib,
        ]);
    }

}