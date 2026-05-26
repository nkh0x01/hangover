import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../diagnostics/presentation/driver_build_identity_label.dart';

class WelcomePage extends StatelessWidget {
  const WelcomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: Insets.l),
              const Row(
                children: [
                  BrandLogo(size: BrandLogoSize.m),
                  Spacer(),
                  StatusPill(label: 'Driver', tone: StatusTone.accent),
                ],
              ),
              const SizedBox(height: Insets.s),
              const DriverBuildIdentityLabel(),
              const Spacer(),
              Text(
                'Ride 360 Driver',
                style: Theme.of(context).textTheme.headlineLarge,
              ),
              const SizedBox(height: Insets.s),
              Text(
                'აირჩიეთ მოქმედება, რომ სწორად გავაგრძელოთ.',
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: Insets.xxl),
              _ChoiceButton(
                icon: Icons.login_rounded,
                title: 'შესვლა',
                subtitle: 'თუ უკვე დარეგისტრირებული ხართ მძღოლად',
                onTap: () => context.go('/auth/phone?mode=login'),
              ),
              const SizedBox(height: Insets.m),
              _ChoiceButton(
                icon: Icons.assignment_ind_rounded,
                title: 'მძღოლად რეგისტრაცია',
                subtitle: 'შეავსეთ განაცხადი და დაელოდეთ დადასტურებას',
                onTap: () => context.go('/auth/phone?mode=registration'),
              ),
              const Spacer(),
              TextButton.icon(
                onPressed: () => context.push('/diagnostics'),
                icon: const Icon(Icons.info_outline_rounded),
                label: const Text('დიაგნოსტიკა'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ChoiceButton extends StatelessWidget {
  const _ChoiceButton({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(Radii.m),
      child: InkWell(
        borderRadius: BorderRadius.circular(Radii.m),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(Insets.l),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(Radii.m),
            border: Border.all(color: AppColors.outlineSubtle),
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.surfaceVariant,
                  borderRadius: BorderRadius.circular(Radii.m),
                ),
                child: Icon(icon, color: AppColors.ink),
              ),
              const SizedBox(width: Insets.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded),
            ],
          ),
        ),
      ),
    );
  }
}
