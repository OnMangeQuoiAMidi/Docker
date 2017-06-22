<?php

namespace AppBundle\Controller;

use AppBundle\Entity\OrderGroup;
use AppBundle\Entity\Place;
use AppBundle\Form\OrderGroupType;
use AppBundle\Form\OrderGroupNoJsType;
use AppBundle\Form\PlaceType;
use Ivory\GoogleMap\Base\Coordinate;
use Ivory\GoogleMap\Map;
use Ivory\GoogleMap\MapTypeId;
use Ivory\GoogleMap\Overlay\InfoWindow;
use Ivory\GoogleMap\Overlay\Marker;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Place controller.
 *
 * @Route("/place")
 */
class PlaceController extends Controller
{
    /**
     * Lists all Place entities.
     *
     * @Route("/", name="place_index")
     * @Method("GET")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $places = $em->getRepository('AppBundle:Place')->findAll();

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $places,
            $request->query->getInt('page', 1),
            4
        );

        $map = new Map();

        // Disable the auto zoom flag (disabled by default)
        $map->setAutoZoom(false);

        // Sets the center
        $map->setCenter(new Coordinate(44.841767, -0.574961));

        // Sets the zoom
        $map->setMapOption('zoom', 14);

        foreach ($places as $place) {
            if ($place->getLongitude() && $place->getLatitude()) {
                $marker = new Marker(new Coordinate($place->getLatitude(), $place->getLongitude()));
                $marker->setInfoWindow(new InfoWindow($place->getName()));
                $map->getOverlayManager()->addMarker($marker);
            }
        }

        $map->setStaticOption('maptype', MapTypeId::HYBRID);
        $map->setStylesheetOption('width', '100%');

        return $this->render('place/index.html.twig', array(
            'pagination' => $pagination,
            'map' => $map
        ));
    }

    /**
     * Creates a new Place entity.
     *
     * @Route("/new", name="place_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $place = new Place();
        $form = $this->createForm('AppBundle\Form\PlaceType', $place);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($place);

            $em->flush();

            $request->getSession()
                ->getFlashBag()
                ->add('success', 'New place saved in database.');

            return $this->redirectToRoute('place_show', array('place' => $place->getId()));
        }

        return $this->render('place/new.html.twig', array(
            'place' => $place,
            'form' => $form->createView()
        ));
    }

    /**
     * Finds and displays a Place entity.
     *
     * @Route("/{place}", name="place_show")
     * @Method({"GET", "POST"})
     */
    public function showAction(Request $request, Place $place)
    {
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();

        $tokenPath = null;

        $delete_form = $this->createDeleteForm($place);

        $orderGroup = new OrderGroup();
        $formOrder = $this->createForm(OrderGroupType::class, $orderGroup, array(
            'method' => 'POST',
        ));

        $formOrderNoJs = $this->createForm(OrderGroupNoJsType::class, $orderGroup, array(
            'method' => 'POST',
        ));

        if($request->isXmlHttpRequest()) {

            if($user) {
                
                $formOrder->handleRequest($request);

                $expiration_date = $request->get('expiration_date');
                $expiration_date = str_replace('/', '-', $expiration_date);
                $formatted_expiration_date = new \DateTime($expiration_date, new \DateTimeZone('Europe/Paris'));

                $now = new \DateTime("now", new \DateTimeZone('Europe/Paris'));
                $now->modify('+2 hour');

                if ($formatted_expiration_date >= $now) {

                    $token = uniqid();

                    $orderGroup->setToken($token);
                    $orderGroup->setExpirationDate($formatted_expiration_date);
                    $orderGroup->setPlace($place);
                    $orderGroup->setUser($user);
                    $em->persist($orderGroup);
                    $em->flush();

                    return new JsonResponse($token);

                }
                else {
                    return new JsonResponse('La date est incorrect');
                }
            
            } else {
                return new JsonResponse(false);
            }
        }
        else {

            if ($request->isMethod('POST')) {

                $formOrderNoJs->handleRequest($request);

                if ($formOrderNoJs->isValid()) {

                    $token = uniqid();
                    $tokenPath = 'http:'.$this->generateUrl('order_group_show', ['token' => $token], 3);

                    $orderGroup->setToken($token);
                    $orderGroup->setPlace($place);
                    $orderGroup->setUser($user);
                    $em->persist($orderGroup);
                    $em->flush();
                }
            }
        

            return $this->render('place/show.html.twig', array(
                'place' => $place,
                'delete_form' => $delete_form->createView(),
                'formOrderGroup' => $formOrder->createView(),
                'formOrderGroupNoJs' => $formOrderNoJs->createView(),
                'tokenPath' => $tokenPath
            ));
        }
    }

    /**
     * Displays a form to edit an existing Place entity.
     *
     * @Route("/{place}/edit", name="place_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Place $place)
    {
        $editForm = $this->createForm('AppBundle\Form\PlaceType', $place);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $em->persist($place);
            $em->flush();

            $request->getSession()
                ->getFlashBag()
                ->add('success', 'Place saved to database.');

            return $this->redirectToRoute('place_show', array('place' => $place->getId()));
        }

        return $this->render('place/edit.html.twig', array(
            'place' => $place,
            'edit_form' => $editForm->createView(),
        ));
    }

    /**
     * Deletes a Place entity.
     *
     * @Route("/{place}", name="place_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Place $place)
    {
        $form = $this->createDeleteForm($place);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();

            $em->remove($place);
            $em->flush();

            $request->getSession()
                ->getFlashBag()
                ->add('success', 'Place removed from database.');
        }

        return $this->redirectToRoute('place_index');
    }

    /**
     * Creates a form to delete a Place entity.
     *
     * @param Place $place The Place entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Place $place)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('place_delete', array('place' => $place->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
