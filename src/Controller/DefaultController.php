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

use Symfony\Component\HttpFoundation\Cookie;

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
        $jourDistribs = $jourDistribRepository->findAllActive();

        return $this->render('passe_commande/index.html.twig', [
            'lastNom' => $this->session->get('commande_nom'),
            'lastPrenom' => $this->session->get('commande_prenom'),
            'jour_distribs' => $jourDistribs,
            'poid_restant' => $jourDistribRepository->findPoid(),
        ]);
    }

    /**
     * @Route("/synthese/{suivi}", name="synthese_index", methods={"GET"})
     */
    public function synthese(JourDistribRepository $jourDistribRepository, int $suivi ): Response
    {
        switch ($suivi) {
            case 0:
                $jourDistribs = $jourDistribRepository->findAllActive();
                break;
            case 1:
                $order = 'DESC';
                $limit = 100;
                $jourDistribs = $jourDistribRepository->findAllOrder($order, $limit);
                break;
            case 2:
                $order = 'DESC';
                $limit = 100;
                $jourDistribs = $jourDistribRepository->findAllOrder($order, $limit);
                break;
            default:
                $order = 'ASC';
                $jourDistribs = null;
                break;
        }
        return $this->render('passe_commande/synthese.html.twig', [
            'jour_distribs' => $jourDistribs,
            'suivi' => $suivi
        ]);
    }

    /**
     * @Route("/livree/{commandeId}", name="livree_commande", methods={"GET"})
     */
    public function livreeCommande(CommandeRepository $commandeRepository, int $commandeId ): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $commande = $entityManager->getRepository(Commande::class)->find($commandeId);
        $commande->setLivree(true);
        
        $entityManager->persist($commande);
        $entityManager->flush();

        return $this->redirectToRoute('synthese_index',['suivi' => 1]);
    }

    /**
     * @Route("/synthesepoids", name="synthese_poids", methods={"GET"})
     */
    public function synthesePoids(JourDistribRepository $jourDistribRepository, PainRepository $painRepository): Response
    {
        $jourDistribs = $jourDistribRepository->findAllOrder('DESC');
        $dates = [];
        foreach ($jourDistribs as $jourDistrib ) {
            
            $painsJour = [];
            
            $pains = $painRepository->findAll();
            foreach ($pains as $pain) {
                $poid = $jourDistribRepository->findPoidPains($jourDistrib->getId(), $pain->getId() );
                if (is_array( $poid )){
                    
                    $poidPain = [ "nom" => $pain->getNom() . " - " . $pain->getPoid() . " kg" ];
                    $poidPain += [ "id" => $pain->getId() ];
                    $poidPain += [ "poid" => $jourDistribRepository->findPoidPains($jourDistrib->getId(), $pain->getId() ) ];
                    
                }
                array_push($painsJour, $poidPain);
            }
            $date = [ 
                "date" => $jourDistrib->getDate(),
                "pains" => $painsJour,
            ];
            array_push($dates, $date);
        }
        // dump ($dates);
        // die;
        return $this->render('passe_commande/synthese_poids.html.twig', [
            'poidsDates' => $dates,
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
        if ( $jourDistrib->getClosed() === false ) {

            $request = Request::createFromGlobals();
            $cookie = $request->cookies->get('commande');
            $nom = "";
            $prenom = "";
            if (isset($cookie)) {
                $contentCookie = json_decode($cookie);
                $nom = $contentCookie->nom;
                $prenom = $contentCookie->prenom;
            }
    
            $form = $this->createForm(CommandeType::class, $commande, [
                'pains' => $pains, 
                'idJourDistrib' => $idJourDistrib, 
                'jourDistrib' => $jourDistrib,
                'lastNom' => $nom,
                'lastPrenom' => $prenom,
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
    
                    $cookieValue = [
                        'command_id' => $commande->getId(),
                        'nom' => $commande->getNom(),
                        'prenom' => $commande->getPrenom(),
                    ];
    
                    $coockie = new Cookie('commande', json_encode($cookieValue), time() + ( 2 * 365 * 24 * 60 * 60));
                    $response = $this->redirectToRoute('commande_index');
                    $response->headers->setCookie($coockie);
    
                    $this->addFlash(
                        'success',
                        'La commande a été enregistrée'
                    );            
                        
                    return $response;
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
        else {
            $this->addFlash(
                'warning',
                'La commande est fermée'
            );
            return $this->redirectToRoute('passe_commande_index');
        }

    }

}