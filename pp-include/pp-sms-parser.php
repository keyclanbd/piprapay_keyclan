<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// Sender rules and SMS formats, used by the signed device API.
function pp_sms_provider_senders() {
                        $mfs_providers = [
                            'NAGAD' => 'Nagad',
                            'bKash' => 'bKash',
                            '16216' => 'Rocket',
                            'upay' => 'Upay',
                            'tap.' => 'Tap',
                            '16269' => 'OkWallet',
                            'IBBL .' => 'Cellfin',
                            'IPAY' => 'Ipay',
                            'iPAY' => 'Ipay',
                            'PathaoPay' => 'Pathao Pay',
                            'PUBALI_BANK' => 'Pubali Bank',
                            'AIBL' => 'Al-Arafah Bank'
                        ];
    return $mfs_providers;
}

// Returns null when the sender isn't a known provider.
function pp_parse_forwarded_sms($from, $text, $receivedStamp) {
    $mfs_providers = pp_sms_provider_senders();
                        // Sender names can be faked, so these are saved for an admin to check and never
                        // approved automatically. Only messages matching a format below are kept, which
                        // leaves out debits, OTPs and offers.
                        $review_only_providers = ['Pubali Bank', 'Al-Arafah Bank'];

                        // Match provider — STRICT by sender only (no text-based matching)
                        $matchFound = false;
                        $matchedFullName = '';

                        $sender = trim($from); // raw sender as received
                        $senderLower = mb_strtolower($sender, 'UTF-8'); // normalize for case-insensitive compare

                        // Normalize provider keys for comparison (case-insensitive)
                        foreach ($mfs_providers as $short => $full) {
                            // compare the sender to the provider-key using case-insensitive equality
                            if (strcasecmp($sender, $short) === 0) {
                                $matchFound = true;
                                $matchedFullName = $full;
                                break;
                            }

                            // additionally allow numeric sender matches where provider key might be numeric string
                            // (This is redundant with strcasecmp but keeps intent explicit)
                            if (is_numeric($short) && $sender === $short) {
                                $matchFound = true;
                                $matchedFullName = $full;
                                break;
                            }
                        }

                        // If you prefer tolerant matching for keys that include small formatting differences
                        // (example: some senders may come as 'bkash' or 'bKash' or 'BKASH'),
                        // the above strcasecmp already handles case-insensitivity.
                        // IMPORTANT: we DO NOT check $text here — no body-based matching.

    if (!$matchFound) {
        return null;
    }

                            $sms_status = "review";

                            $provider_formats = [
                                'bKash' => [
                                    [
                                        'type' => 'sms1',
                                        'format' => '/Cash In Tk (?<amount>[\d,]+\.\d{2}) from (?<mobile>\d+) successful\. Fee Tk (?<fee>[\d,]+\.\d{2})\. Balance Tk (?<balance>[\d,]+\.\d{2})\. TrxID (?<trxid>\w+) at (?<datetime>\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})/'
                                    ],
                                    [
                                        'type' => 'sms2',
                                        'format' => '/You have received Tk (?<amount>[\d,]+\.\d{2}) from (?<mobile>\d+)\. Fee Tk (?<fee>[\d,]+\.\d{2})\. Balance Tk (?<balance>[\d,]+\.\d{2})\. TrxID (?<trxid>\w+) at (?<datetime>\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})/'
                                    ],
                                    [
                                        'type' => 'sms3',
                                        'format' => '/You have received Tk (?<amount>[\d,]+\.\d{2}) from (?<mobile>\d+)\. Ref .*?Fee Tk (?<fee>[\d,]+\.\d{2})\. Balance Tk (?<balance>[\d,]+\.\d{2})\. TrxID (?<trxid>\w+) at (?<datetime>\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})/'
                                    ],
                                    [
                                        'type' => 'sms4',
                                        'format' => '/You have received payment Tk (?<amount>[\d,]+\.\d{2}) from (?<mobile>\d+)\. Fee Tk (?<fee>[\d,]+\.\d{2})\. Balance Tk (?<balance>[\d,]+\.\d{2})\. TrxID (?<trxid>\w+) at (?<datetime>\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})/'
                                    ],
                                    [
                                        'type' => 'sms5',
                                        'format' => '/You have received Tk (?<amount>[\d,]+\.\d{2}) from (?<mobile>\d+)\.Ref .*?TrxID (?<trxid>\w+) at (?<datetime>\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})/'
                                    ]
                                ],
                                'Nagad' => [
                                    [
                                        'type' => 'sms1',
                                        'format' => '/Cash In Received\.\nAmount: Tk (?<amount>[\d,]+\.\d{2})\nUddokta: (?<mobile>\d+)\nTxnID: (?<trxid>[A-Z0-9]+)\nBalance: (?<balance>[\d,]+\.\d{2})\n(?<date>\d{2}\/\d{2}\/\d{4}) (?<time>\d{2}:\d{2})/'
                                    ],
                                    [
                                        'type' => 'sms2',
                                        'format' => '/Money Received\.\nAmount: Tk (?<amount>[\d,]+\.\d{2})\nSender: (?<mobile>\d+)\nRef: (.+)\nTxnID: (?<trxid>\w+)\nBalance: Tk (?<balance>[\d,]+\.\d{2})\n(?<date>\d{2}\/\d{2}\/\d{4}) (?<time>\d{2}:\d{2})/'
                                    ]
                                ],
                                'Upay' => [
                                    [
                                        'type' => 'sms1',
                                        'format' => '/Cash In Received\.\nAmount: Tk (?<amount>[\d,]+\.\d{2})\nUddokta: (?<mobile>\d+)\nTxnID: (?<trxid>[A-Z0-9]+)\nBalance: (?<balance>[\d,]+\.\d{2})\n(?<date>\d{2}\/\d{2}\/\d{4}) (?<time>\d{2}:\d{2})/'
                                    ],
                                    [
                                        'type' => 'sms2',
                                        'format' => '/Tk\. (?<amount>[\d,]+\.\d{2}) has been received from (?<mobile>\d+)\. Ref-.*?Balance Tk\. (?<balance>[\d,]+\.\d{2})\. TrxID (?<trxid>\w+) at (?<datetime>\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})\./'
                                    ]
                                ],
                                'Rocket' => [
                                    // Rocket SMS1: A/C received
                                    [
                                        'type' => 'sms1',
                                        'format' => '/Tk(?<amount>[\d,]+(?:\.\d{1,2})?)\s+received\s+from\s+A\/C:(?:\*+)?(?<mobile>\d+)\s*Fee:Tk\.?(?<fee>[\d,]+(?:\.\d{1,2})?|0),?\s*Your\s*A\/C\s*Balance:\s*Tk(?<balance>[\d,]+(?:\.\d{1,2})?)\s*TxnId:(?<trxid>\d+)\s*Date:(?<datetime>\d{2}-[A-Z]{3}-\d{2}\s+\d{2}:\d{2}:\d{2}\s*(?:am|pm))/i'
                                    ],

                                    // Rocket SMS2: Card credited
                                    [
                                        'type' => 'sms2',
                                        'format' => '/Tk(?<amount>[\d,]+(?:\.\d{1,2})?)\s+credited\s+from\s+card\s+\**(?<mobile>\d+)\.?\s*Fee:Tk\.?(?<fee>[\d,]+(?:\.\d{1,2})?|0)\s*NetBal:Tk(?<balance>[\d,]+(?:\.\d{1,2})?)\s*TxnId:(?<trxid>\d+)\s*Date:(?<datetime>\d{2}-[A-Z]{3}-\d{2}\s+\d{2}:\d{2}:\d{2}\s*(?:am|pm))/i'
                                    ]
                                ]
,
                                'Pubali Bank' => [
                                    // Bangla QR payment, the only bank message with a TrxID
                                    [
                                        'type' => 'sms1',
                                        'format' => '/BDT\s*(?<amount>[\d,]+(?:\.\d{1,2})?)\s+RECEIVED\s+FROM\s+(?<mobile>[\d*]+)\s+TO\s+ACC\s+[\d*]+\s*,.*?USING\s+BANGLA\s+QR.*?TrxID:\s*(?<trxid>\w+)/is'
                                    ],
                                    // Fund transfer from another bank, which has no TrxID
                                    [
                                        'type' => 'sms2',
                                        'format' => '/BDT\s*(?<amount>[\d,]+(?:\.\d{1,2})?)\s+has\s+been\s+credited\s+to\s+your\s+account\s+using\s+.+?\s+from\s+(?<mobile>.+?)\s+on\s+\d{2}-[A-Z]{3}-\d{2}\.\s*A\/C\s+Bal:\s*Cr\s*(?<balance>\d[\d,]*(?:\.\d+)?)/is'
                                    ]
                                ],
                                'Al-Arafah Bank' => [
                                    // Written from the bank's debit message; adjust if a real credit message differs.
                                    [
                                        'type' => 'sms1',
                                        'format' => '/Your\s+A\/C\s+[\d*]+\s+has\s+been\s+Credited\s+by\s+BDT\s*(?<amount>[\d,]+(?:\.\d{1,2})?)\s+on\s+.+?Ref:\s*(?<trxid>\w+)\s+Balance:\s*(?<balance>\d[\d,]*(?:\.\d+)?)/is'
                                    ]
                                ]

                                //just basic add as you need.
                            ];

                            foreach ($provider_formats as &$formats) {
                                foreach ($formats as &$entry) {
                                    if (isset($entry['format'])) {
                                        $entry['format'] = str_replace('\n', '\s*', $entry['format']);
                                    }
                                }
                            }

                            $matched_data = [
                                'amount' => '0',
                                'mobile' => '--',
                                'trxid' => '--',
                                'balance' => '0',
                                'datetime' => $receivedStamp
                            ];

                            $parsed = false;

                            if (isset($provider_formats[$matchedFullName])) {
                                foreach ($provider_formats[$matchedFullName] as $formatData) {
                                    if (preg_match($formatData['format'], $text, $matches)) {
                                        $parsed = true;
                                        $matched_data['amount'] = str_replace(',', '', $matches['amount'] ?? '0');
                                        $matched_data['mobile'] = $matches['mobile'] ?? '--';
                                        $matched_data['trxid'] = $matches['trxid'] ?? '--';
                                        $matched_data['balance'] = str_replace(',', '', $matches['balance'] ?? '0');

                                        if (isset($matches['datetime'])) {
                                            $matched_data['datetime'] = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $matches['datetime'])));
                                        } elseif (isset($matches['date']) && isset($matches['time'])) {
                                            $matched_data['datetime'] = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $matches['date']) . ' ' . $matches['time']));
                                        }

                                        if (in_array($matchedFullName, $review_only_providers, true)) {
                                            $matched_data['datetime'] = getCurrentDatetime('Y-m-d H:i:s');
                                        } else {
                                            $sms_status = "approved";
                                        }
                                        break;
                                    }
                                }
                            }

    return [
        'provider' => $matchedFullName,
        'status' => $sms_status,
        'data' => $matched_data,
        'store' => $parsed || !in_array($matchedFullName, $review_only_providers, true),
    ];
}
