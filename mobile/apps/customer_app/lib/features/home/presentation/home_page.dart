import 'package:flutter/material.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Hangover Mobility')),
      body: const Center(child: Text('Map + ride flow lands in Phase 2/3')),
    );
  }
}
