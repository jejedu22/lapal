<?php

namespace App\Controller;

use App\Entity\JourDistrib;
use App\Form\JourDistribType;
use App\Repository\JourDistribRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
        }

        return $this->redirectToRoute('jour_distrib_index');
    }
}
