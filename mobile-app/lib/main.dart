import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

const channel = MethodChannel('forwarder');
const defaultSite = 'https://pay.keyclan.com';

Future<T?> call<T>(String method, [Map<String, dynamic>? args]) =>
    channel.invokeMethod<T>(method, args);

Future<List<Map<String, dynamic>>> callList(String method, [Map<String, dynamic>? args]) async {
  final rows = await channel.invokeListMethod<dynamic>(method, args) ?? [];
  return rows.map((row) => Map<String, dynamic>.from(row as Map)).toList();
}

String formatTime(int millis) {
  final t = DateTime.fromMillisecondsSinceEpoch(millis);
  String two(int n) => n.toString().padLeft(2, '0');
  return '${t.year}-${two(t.month)}-${two(t.day)} ${two(t.hour)}:${two(t.minute)}';
}

const stateLabels = {
  'waiting': ('Waiting', Colors.orange),
  'forwarded': ('Forwarded', Colors.green),
  'duplicate': ('Duplicate', Colors.blueGrey),
  'rejected': ('Rejected', Colors.red),
  'failed': ('Failed, retrying', Colors.deepOrange),
  'not_selected': ('Not selected', Colors.grey),
  'before_selection': ('Before selection', Colors.grey),
};

class StateChip extends StatelessWidget {
  const StateChip(this.state, {super.key});
  final String state;

  @override
  Widget build(BuildContext context) {
    final (label, color) = stateLabels[state] ?? (state, Colors.grey);
    return Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 12));
  }
}

void main() => runApp(const ForwarderApp());

class ForwarderApp extends StatelessWidget {
  const ForwarderApp({super.key});

  @override
  Widget build(BuildContext context) => MaterialApp(
        title: 'SMS Forwarder',
        theme: ThemeData(colorSchemeSeed: Colors.teal),
        darkTheme: ThemeData(colorSchemeSeed: Colors.teal, brightness: Brightness.dark),
        home: const Home(),
      );
}

class Home extends StatefulWidget {
  const Home({super.key});

  @override
  State<Home> createState() => _HomeState();
}

class _HomeState extends State<Home> with WidgetsBindingObserver {
  int tab = 0;
  Map<String, dynamic>? state;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load(refresh: true);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState lifecycle) {
    if (lifecycle == AppLifecycleState.resumed) load(refresh: true);
  }

  Future<void> load({bool refresh = false}) async {
    if (refresh) {
      try {
        await call('refresh');
      } on PlatformException catch (_) {
        // Offline is normal here; the status screen shows the saved state.
      }
    }
    final value = await channel.invokeMapMethod<String, dynamic>('state');
    if (mounted) setState(() => state = value);
  }

  @override
  Widget build(BuildContext context) {
    final current = state;
    if (current == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (current['status'] == 'unpaired') return PairScreen(onDone: () => load(refresh: true));

    final pages = [
      StatusPage(state: current, onChanged: load),
      const SendersPage(),
      const HistoryPage(),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('SMS Forwarder')),
      body: SafeArea(child: pages[tab]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: tab,
        onDestinationSelected: (value) {
          setState(() => tab = value);
          load();
        },
        destinations: const [
          NavigationDestination(icon: Icon(Icons.shield_outlined), label: 'Status'),
          NavigationDestination(icon: Icon(Icons.inbox_outlined), label: 'Senders'),
          NavigationDestination(icon: Icon(Icons.history), label: 'History'),
        ],
      ),
    );
  }
}

class PairScreen extends StatefulWidget {
  const PairScreen({super.key, required this.onDone});
  final VoidCallback onDone;

  @override
  State<PairScreen> createState() => _PairScreenState();
}

class _PairScreenState extends State<PairScreen> {
  final site = TextEditingController(text: defaultSite);
  final code = TextEditingController();
  String? error;
  bool busy = false;

  Future<void> pair() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await call('pair', {'site': site.text, 'code': code.text});
      widget.onDone();
    } on PlatformException catch (e) {
      setState(() => error = e.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Connect to website')),
        body: ListView(padding: const EdgeInsets.all(20), children: [
          const Text('In admin, open Devices, then Connect Android App, and create a pairing code.'),
          const SizedBox(height: 16),
          // Release builds can only pair with the real site, so the address isn't editable there.
          if (kDebugMode) ...[
            TextField(
              controller: site,
              keyboardType: TextInputType.url,
              decoration: const InputDecoration(labelText: 'Website address', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 12),
          ],
          TextField(
            controller: code,
            textCapitalization: TextCapitalization.characters,
            decoration: const InputDecoration(labelText: 'Pairing code', border: OutlineInputBorder()),
          ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
          const SizedBox(height: 16),
          FilledButton(onPressed: busy ? null : pair, child: Text(busy ? 'Connecting...' : 'Connect')),
        ]),
      );
}

class StatusPage extends StatelessWidget {
  const StatusPage({super.key, required this.state, required this.onChanged});
  final Map<String, dynamic> state;
  final Future<void> Function({bool refresh}) onChanged;

  @override
  Widget build(BuildContext context) {
    final status = state['status'] as String;
    final counts = Map<String, dynamic>.from(state['counts'] as Map);
    final error = state['lastError'] as String;

    return RefreshIndicator(
      onRefresh: () => onChanged(refresh: true),
      child: ListView(padding: const EdgeInsets.all(16), children: [
        Card(
          child: ListTile(
            leading: Icon(status == 'active' ? Icons.verified_user : Icons.hourglass_top,
                color: status == 'active' ? Colors.green : Colors.orange),
            title: Text(switch (status) {
              'active' => 'Connected',
              'pending' => 'Waiting for admin approval',
              'revoked' => 'Removed by admin',
              _ => status,
            }),
            subtitle: Text(state['site'] as String),
          ),
        ),
        if (status == 'pending')
          Card(
            child: ListTile(
              title: Text('Match code: ${state['matchCode']}'),
              subtitle: const Text('Approve this phone in admin only if the same code is shown there.'),
            ),
          ),
        if (error.isNotEmpty)
          Card(child: ListTile(leading: const Icon(Icons.error_outline), title: Text(error))),
        if (state['hasSms'] != true)
          Card(
            child: ListTile(
              title: const Text('SMS permission is needed'),
              trailing: FilledButton(onPressed: () => call('requestSms'), child: const Text('Allow')),
            ),
          ),
        if (state['batteryExempt'] != true)
          Card(
            child: ListTile(
              title: const Text('Stop Android from sleeping this app'),
              subtitle: const Text('Without this, forwarding can be delayed.'),
              trailing: FilledButton(onPressed: () => call('requestBattery'), child: const Text('Fix')),
            ),
          ),
        Card(
          child: Column(children: [
            for (final key in const ['waiting', 'failed', 'forwarded', 'duplicate', 'rejected'])
              ListTile(dense: true, title: StateChip(key), trailing: Text('${counts[key] ?? 0}')),
          ]),
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: () async {
            await call('syncNow');
            await onChanged(refresh: true);
          },
          icon: const Icon(Icons.sync),
          label: const Text('Check and send now'),
        ),
        TextButton(
          onPressed: () async {
            final yes = await showDialog<bool>(
              context: context,
              builder: (dialog) => AlertDialog(
                title: const Text('Disconnect this phone?'),
                content: const Text('Selected senders and this phone\'s history will be cleared.'),
                actions: [
                  TextButton(onPressed: () => Navigator.pop(dialog, false), child: const Text('Cancel')),
                  TextButton(onPressed: () => Navigator.pop(dialog, true), child: const Text('Disconnect')),
                ],
              ),
            );
            if (yes == true) {
              await call('unpair');
              await onChanged();
            }
          },
          child: const Text('Disconnect'),
        ),
      ]),
    );
  }
}

class SendersPage extends StatefulWidget {
  const SendersPage({super.key});

  @override
  State<SendersPage> createState() => _SendersPageState();
}

class _SendersPageState extends State<SendersPage> {
  List<Map<String, dynamic>>? senders;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final rows = await callList('senders');
      if (mounted) setState(() => senders = rows);
    } on PlatformException catch (e) {
      if (mounted) setState(() => error = e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (error != null) {
      return const Center(child: Text('Allow SMS permission on the Status tab first.'));
    }
    final rows = senders;
    if (rows == null) return const Center(child: CircularProgressIndicator());
    if (rows.isEmpty) return const Center(child: Text('No SMS found on this phone.'));

    return RefreshIndicator(
      onRefresh: load,
      child: ListView.builder(
        itemCount: rows.length,
        itemBuilder: (context, index) {
          final row = rows[index];
          final address = row['address'] as String;
          final supported = row['supported'] == true;
          return SwitchListTile(
            value: row['enabled'] == true,
            title: Text(address),
            subtitle: GestureDetector(
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => MessagesPage(sender: address)),
              ),
              child: Text(
                '${row['count']} messages · ${supported ? 'Supported' : 'Not supported on website'} · View',
                style: TextStyle(color: supported ? Colors.green : null),
              ),
            ),
            onChanged: (value) async {
              await call('setSender', {'sender': address, 'enabled': value});
              await load();
            },
          );
        },
      ),
    );
  }
}

class MessagesPage extends StatelessWidget {
  const MessagesPage({super.key, required this.sender});
  final String sender;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(sender)),
        body: FutureBuilder(
          future: callList('messages', {'sender': sender}),
          builder: (context, snapshot) {
            final rows = snapshot.data;
            if (rows == null) return const Center(child: CircularProgressIndicator());
            return ListView.separated(
              itemCount: rows.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, index) => ListTile(
                title: Text(rows[index]['body'] as String),
                subtitle: Text(formatTime(rows[index]['time'] as int)),
                trailing: StateChip(rows[index]['state'] as String),
              ),
            );
          },
        ),
      );
}

class HistoryPage extends StatefulWidget {
  const HistoryPage({super.key});

  @override
  State<HistoryPage> createState() => _HistoryPageState();
}

class _HistoryPageState extends State<HistoryPage> {
  String? filter;

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 8),
        child: Row(children: [
          for (final key in const [null, 'waiting', 'failed', 'forwarded', 'duplicate', 'rejected'])
            Padding(
              padding: const EdgeInsets.all(4),
              child: ChoiceChip(
                label: Text(key == null ? 'All' : stateLabels[key]!.$1),
                selected: filter == key,
                onSelected: (_) => setState(() => filter = key),
              ),
            ),
        ]),
      ),
      Expanded(
        child: FutureBuilder(
          key: ValueKey(filter),
          future: callList('history', {'state': filter}),
          builder: (context, snapshot) {
            final rows = snapshot.data;
            if (rows == null) return const Center(child: CircularProgressIndicator());
            if (rows.isEmpty) return const Center(child: Text('Nothing here yet.'));
            return ListView.separated(
              itemCount: rows.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final row = rows[index];
                final reason = row['reason'] as String;
                return ListTile(
                  title: Text(row['sender'] as String),
                  subtitle: Text(
                    '${row['body']}\n${formatTime(row['time'] as int)}${reason.isEmpty ? '' : ' · $reason'}',
                  ),
                  isThreeLine: true,
                  trailing: StateChip(row['state'] as String),
                );
              },
            );
          },
        ),
      ),
    ]);
  }
}
