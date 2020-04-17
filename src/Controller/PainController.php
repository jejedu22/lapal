<?php

namespace App\Controller;

use App\Entity\Pain;
use App\Form\PainType;
use App\Repository\PainRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
            'pains' => $painRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="pain_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $pain = new Pain();
        $form = $this->createForm(PainType::class, $pain);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($pain);
            $entityManager->flush();

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

            return $this->redirectToRoute('pain_index');
        }

        return $this->render('pain/edit.html.twig', [
            'pain' => $pain,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="pain_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Pain $pain): Response
    {
        if ($this->isCsrfTokenValid('delete'.$pain->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($pain);
            $entityManager->flush();
        }

        return $this->redirectToRoute('pain_index');
    }
}
