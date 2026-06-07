//
//  ElectricSimApp.swift
//  ElectricSim
//
//  ელექტრიკის სიმულატორი — საგანმანათლებლო თამაში (TN-C-S / IEC).
//  iOS 16+, iPhone + iPad, portrait + landscape.
//

import SwiftUI

@main
struct ElectricSimApp: App {
    @StateObject private var game = GameState()
    @StateObject private var store = EntitlementStore()
    @StateObject private var ads = AdManager()

    var body: some Scene {
        WindowGroup {
            RootView()
                .environmentObject(game)
                .environmentObject(store)
                .environmentObject(ads)
        }
    }
}

struct RootView: View {
    @EnvironmentObject var game: GameState

    var body: some View {
        NavigationStack {
            LevelListView()
        }
        .tint(.brand)
        .onAppear { GameCenterManager.shared.authenticate() }   // არ-მბლოკავი
    }
}
