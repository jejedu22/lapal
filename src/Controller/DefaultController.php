<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeType;
use App\Entity\JourDistrib;

use App\Repository\CommandeRepository;
use App\Repository\JourDistribRepository;
use App\Repository\PainRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DefaultController extends AbstractController
{
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }
    /**
     * @Route("/", name="passe_commande_index", methods={"GET"})
     */
    public function index(JourDistribRepository $jourDistribRepository): Response
    {
        return $this->render('passe_commande/index.html.twig', [
            'lastNom' => $this->session->get('commande_nom'),
            'lastPrenom' => $this->session->get('commande_prenom'),
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
     * @Route("/synthese/poids", name="synthese_poids", methods={"GET"})
     */
    public function synthesePoids(JourDistribRepository $jourDistribRepository, PainRepository $painRepository): Response
    {
        $poids = [];
        $jourDistribs = $jourDistribRepository->findAll();
        foreach ($jourDistribs as $jourDistrib ) {
            if (is_array($jourDistribRepository->findPoids($jourDistrib->getId()))){
                $poidsDate = [ "date" => $jourDistrib->getDate() ];
                $poidsDate += [ "pains" => $jourDistribRepository->findPoids($jourDistrib->getId()) ];
                array_push($poids, $poidsDate);
            }
        }

        return $this->render('passe_commande/synthese_poids.html.twig', [
            'poids' => $poids,
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

        $form = $this->createForm(CommandeType::class, $commande, [
            'pains' => $pains, 
            'idJourDistrib' => $idJourDistrib, 
            'jourDistrib' => $jourDistrib,
            'lastNom' => $this->session->get('commande_nom'),
            'lastPrenom' => $this->session->get('commande_prenom'),
            ]);
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

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($commande);
                $entityManager->flush();

                $this->session->set('commande_id', $commande->getId());
                $this->session->set('commande_nom', $commande->getNom());
                $this->session->set('commande_prenom', $commande->getPrenom());
                
                $this->addFlash(
                    'success',
                    'La commande a été enregistrée'
                    
                );            
                return $this->redirectToRoute('commande_index');
            }
            else {
                $this->addFlash(
                    'warning',
                    'La limite de poid disponible a été dépassée !'
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