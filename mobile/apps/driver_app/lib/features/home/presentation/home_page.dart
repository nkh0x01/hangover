import 'package:flutter/material.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Hangover Driver')),
      body: const Center(child: Text('Online toggle + offers land in Phase 3')),
    );
  }
}
