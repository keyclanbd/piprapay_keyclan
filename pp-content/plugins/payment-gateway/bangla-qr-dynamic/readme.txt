=== Bangla QR Dynamic ===
Contributors: KeyClan
Requires at least: 1.0
Tested up to: 1.0
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate an amount-specific Bangla QR from the merchant QR you already have.

== Description ==

Most Bangla QR codes handed out by banks are static: the customer types the
amount by hand, which is slow and easy to get wrong. This gateway rebuilds the
merchant QR for each checkout with the amount already filled in.

Upload your existing merchant QR image once (or paste its payload). The gateway
decodes the EMVCo TLV data on the server, keeps every merchant field untouched,
and at checkout it:

1. sets the point of initiation to 12 (dynamic),
2. inserts tag 54 with the exact amount due,
3. recalculates the CRC16/CCITT-FALSE checksum in tag 63,
4. renders a fresh PNG for the customer to scan.

Nothing is sent to a third party. The encoder, the EMV parser and the QR reader
are all plain PHP inside this plugin.

== Why not a text QR? ==

A QR built from free text such as "bKash 017xxxx 100" looks like a QR code but
no wallet app can pay it. This gateway never invents data: it only modifies a
real merchant payload your bank issued, so the merchant account, GUI and country
stay exactly as the bank encoded them.

== Installation ==

1. Copy the bangla-qr-dynamic folder to pp-content/plugins/payment-gateway/.
2. In the admin panel open the gateway list and activate Bangla QR Dynamic.
3. Upload your merchant QR image, or paste its payload, then save.

The admin screen shows the decoded merchant name, GUI, currency and checksum so
you can confirm the right QR was read.

== Requirements ==

* PHP 8.0 or newer.
* The GD extension, for reading uploaded QR images and rendering PNG output.
  Without GD the gateway still works if you paste the payload, but the image is
  rendered as SVG instead.
* A BDT merchant QR (EMV tag 53 = 050).

== Notes ==

* The uploaded image is only a reference copy. The stored payload is what the
  checkout uses, so re-uploading is only needed when the merchant QR changes.
* Image decoding handles any scale, 90 degree rotations, screenshots with
  surrounding app UI, tinted card backgrounds and JPEG compression.
* Image decoding does not attempt perspective correction and does not repair
  damaged codewords, so a QR photographed at a steep angle, a heavily corrupted
  image, or a badly cropped one may not decode. Paste the payload in that case;
  the gateway reads the payload with the same validation either way.
* The amount in the QR is taken from the gateway total, including any fixed or
  percent charge configured for this gateway.

== Changelog ==

= 1.0.0 =
* Initial release: EMV TLV parser, dynamic payload builder, CRC16 recalculation,
  pure-PHP QR encoder and QR reader, admin and checkout screens.
