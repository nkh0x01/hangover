//
//  LevelListView.swift
//  ElectricSim
//
//  დონეების სია პროგრესით + მონეტიზაცია (Pro-გეითინგი, რეკლამა, „შესახებ").
//

import SwiftUI

struct LevelListView: View {
    @EnvironmentObject var game: GameState
    @EnvironmentObject var store: StoreManager
    @EnvironmentObject var ads: AdManager
    @State private var showPaywall = false
    @State private var showAbout = false

    /// Pro-კონტენტი: 3-ფაზიანი დონეები საჭიროებს Pro-ს.
    private func requiresPro(_ level: Level) -> Bool { level.phase == .three }

    var body: some View {
        List {
            if let err = game.loadError {
                Section {
                    Label(err, systemImage: "exclamationmark.triangle").foregroundStyle(.red)
                }
            }

            if !store.isPro {
                Section {
                    Button { showPaywall = true } label: {
                        HStack {
                            Image(systemName: "bolt.shield.fill").foregroundStyle(.yellow)
                            VStack(alignment: .leading, spacing: 2) {
                                Text("განბლოკე ელექტრიკი Pro").font(.subheadline.bold())
                                Text("3 ფაზა + მოტორი, რეკლამის გარეშე").font(.caption).foregroundStyle(.secondary)
                            }
                            Spacer()
                            Image(systemName: "chevron.right").font(.caption).foregroundStyle(.secondary)
                        }
                    }
                }
            }

            Section {
                ForEach(game.levels) { level in
                    levelRow(level)
                }
            } header: {
                Text("დონეები")
            } footer: {
                Text("ააწყვე ფარი, დააკავშირე სადენები და ჩართე ძაბვა. შეცდომებზე მიიღებ ახსნას ქართულად.")
            }
        }
        .navigationTitle("ელექტრიკის სიმულატორი")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .navigationBarTrailing) {
                Button { showAbout = true } label: { Image(systemName: "gearshape") }
            }
        }
        .safeAreaInset(edge: .bottom) { AdBannerView() }
        .sheet(isPresented: $showPaywall) {
            PaywallView().environmentObject(store)
        }
        .sheet(isPresented: $showAbout) {
            AboutView().environmentObject(store).environmentObject(game)
        }
    }

    @ViewBuilder
    private func levelRow(_ level: Level) -> some View {
        let unlocked = game.isUnlocked(level)
        let locked = requiresPro(level) && !store.isPro

        if locked {
            Button { showPaywall = true } label: {
                LevelRowContent(level: level, completed: game.isCompleted(level),
                                unlocked: unlocked, proLocked: true)
            }
            .disabled(!unlocked)
        } else {
            NavigationLink {
                WorkbenchView(level: level)
            } label: {
                LevelRowContent(level: level, completed: game.isCompleted(level),
                                unlocked: unlocked, proLocked: false)
            }
            .disabled(!unlocked)
        }
    }
}

private struct LevelRowContent: View {
    let level: Level
    let completed: Bool
    let unlocked: Bool
    let proLocked: Bool

    var body: some View {
        HStack(spacing: 12) {
            ZStack {
                Circle()
                    .fill(completed ? Color.green.opacity(0.2) : Color.yellow.opacity(0.15))
                    .frame(width: 40, height: 40)
                Image(systemName: iconName)
                    .foregroundStyle(iconColor)
            }
            VStack(alignment: .leading, spacing: 2) {
                HStack(spacing: 6) {
                    Text(level.title).font(.headline)
                    if proLocked {
                        Text("PRO")
                            .font(.system(size: 9, weight: .heavy))
                            .padding(.horizontal, 5).padding(.vertical, 1)
                            .background(Color.yellow, in: Capsule())
                            .foregroundStyle(.black)
                    }
                }
                Text(level.brief)
                    .font(.caption)
                    .foregroundStyle(.secondary)
                    .lineLimit(2)
            }
        }
        .padding(.vertical, 4)
    }

    private var iconName: String {
        if proLocked { return "lock.fill" }
        if completed { return "checkmark" }
        return unlocked ? "bolt.fill" : "lock.fill"
    }
    private var iconColor: Color {
        if completed { return .green }
        return unlocked && !proLocked ? .yellow : .secondary
    }
}
