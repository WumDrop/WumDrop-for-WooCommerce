#WumDrop-for-WooCommerce
__________________________________________________________________________________________
To install the WumDrop extension on your WordPress site follow these steps:

Install and activate WooCommerce (and click the button to install the WooCommerce pages when prompted)

Install and activate this extension

Go to WooCommerce > Settings in the admin menu

Set your base location to either the Western Cape or Gauteng

Set your currency to South African Rand

Go to the Shipping tab and click on the WumDrop link

Enable the WumDrop delivery method and fill in all of the other fields - the pick up address field will auto complete from Google and it will fill in the coordinates automatically.

Once you've done that you can go to the frontend and add any product to your cart (see here for adding dummy product data to your site if you need to - makes it easier to test). 

Once you have something in your cart go to the checkout and fill in your address details. 

Once you have filled in a complete shipping address then the WumDrop delivery method will become available in the box at the bottom.

For testing, it's easiest to select bank transfer as your payment method, so just do that and complete the checkout. At this point the order is in the 'pending payment' status as the store will be waiting on the bank transfer.

If the order was placed via PayPal, PayFast, etc. then when you complete checkout the order will be fully paid and the WumDrop delivery order will be placed through the API immediately.

In this test case, however, the order is still pending payment, so now you must go back to the WP dashboard and go to WooCommerce > Orders in the admin menu. The order you just placed will be at the top of the list, so click on the order number to open it up.

Along with all the normal order details, you will see a section title 'WumDrop Delivery Status' (just below the shipping address). At the moment it will say 'No delivery ordered' with a link to place the order. Above that, however, is the order status select box that currently says 'Pending Payment' - in this box select 'Processing' and then hit the 'save order' button. This is the standard method of updating an order once the bank transfer has been made.

When you do this a number of things happen:
The WumDrop delivery order is placed via your API
If the delivery order is successful then the order status is automatically transitioned to 'complete'
The delivery ID is saved to the order meta
A note is added to the order stating that the WumDrop delivery order has been placed
Once the page has reloaded you will see that the order status select box now reads 'Complete' and the WumDrop status now reads as 'Pending pickup' with a link to cancel the pickup if you need to do so.

Once the WumDrop delivery order has been placed, the customer will see the current delivery status on their frontend view of the order details.

The other scenario that could happen is when the order is placed, but the items in it are out of stock. In this case the order status is set to 'on hold' and, once the store has stock again, the store owner must manually set the order status to pending. At that point all the normal WumDrop actions will take place regarding the delivery order.

This is actually due to a bit of a shortcoming in WooCommerce itself with regards to handling backordered items, but I'm still looking to see if I can find an automated way to work around this.

__________________________________________________________________________________________
