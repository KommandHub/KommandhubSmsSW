# 0.9.0-beta.1

Première bêta publique. Fonctionnellement complète pour les SMS
transactionnels ; la distribution par les fournisseurs a été vérifiée face à
leurs réponses d'erreur, mais pas encore sur un grand volume d'envois réels —
c'est précisément l'objet de la période bêta. Commence avec un expéditeur de
test et une audience réduite avant de basculer une boutique.

- Envoie des SMS transactionnels en complément des e-mails de Shopware. Une
  action « Envoyer un SMS » dans le Flow Builder distribue un modèle de SMS pour
  n'importe quel événement — commande passée, commande expédiée, réinitialisation
  du mot de passe — sans remplacer l'e-mail.
- Gère les modèles de SMS par événement dans l'Administration, avec un compteur
  en direct de caractères et de segments qui signale quand un message déborde
  sur un second segment facturé ou passe en Unicode.
- Choisis parmi quatre fournisseurs de SMS, avec bascule automatique : Termii
  (Afrique de l'Ouest), Sendexa (Ghana, bêta), Africa's Talking (Afrique de
  l'Est) et Twilio (repli international). Définis un fournisseur par défaut ; le
  plugin préfère celui qui couvre le pays de destination et se rabat sur les
  autres.
- Aucun fournisseur par défaut n'est présélectionné : choisis-en un et
  configure-le avant la mise en production, pour qu'une boutique n'envoie jamais
  via un fournisseur non paramétré.
- « Envoyer un message de test » sur chaque modèle distribue à un numéro de ton
  choix, avec des données de commande d'exemple, et indique précisément pourquoi
  un envoi a échoué.
- Les champs de téléphone de la boutique reçoivent un sélecteur d'indicatif pays
  à côté du numéro, de sorte que le pays soit le choix du client plutôt qu'un
  réglage unique à l'échelle de la boutique. Fonctionne pour l'inscription, la
  gestion des adresses, le paiement et les formulaires CMS.
- Un client sans numéro mobile utilisable est ignoré et journalisé ; le reste du
  flux, y compris l'e-mail de confirmation de commande, se poursuit.
- Les webhooks de statut de livraison sont reçus et journalisés, avec
  vérification de signature par canal de vente.
