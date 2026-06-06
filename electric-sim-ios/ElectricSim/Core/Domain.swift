//
//  Domain.swift
//  ElectricSim — Core
//
//  ელექტრო-მონტაჟის სიმულატორის ძირითადი მონაცემთა მოდელი.
//  მთლიანად Foundation-ზე დაფუძნებული (UIKit/SwiftUI გარეშე), რათა
//  გამოსაცდელი იყოს `swift test`-ით ნებისმიერ პლატფორმაზე.
//
//  სისტემა: TN-C-S (PEN ფარში იყოფა PE-დ და N-ად).
//  ძაბვები: 1 ფაზა L–N = 230 V; 3 ფაზა L–L = 400 V, L–N = 230 V.
//

import Foundation

// MARK: - Constants

public enum Electrical {
    /// ფაზა–ნული ძაბვა (V).
    public static let phaseToNeutral: Double = 230
    /// ფაზა–ფაზა ძაბვა (V).
    public static let phaseToPhase: Double = 400
    /// RCD-ის სტანდარტული გამშვები დენი როზეტებზე (mA).
    public static let standardRCDmA: Double = 30
}

// MARK: - Conductor (გამტარი)

/// ელექტრული გამტარის ლოგიკური დანიშნულება.
public enum Conductor: String, Codable, CaseIterable, Sendable {
    case L   // ერთფაზიანი ფაზა
    case L1  // სამფაზიანი ფაზა 1
    case L2  // სამფაზიანი ფაზა 2
    case L3  // სამფაზიანი ფაზა 3
    case N   // ნული (neutral)
    case PE  // დამცავი მიწა (protective earth)

    /// არის თუ არა „ცხელი" გამტარი (ფაზა).
    public var isHot: Bool {
        switch self {
        case .L, .L1, .L2, .L3: return true
        case .N, .PE: return false
        }
    }
}

// MARK: - Wire color (IEC 60446 / ჰარმონიზებული ფერები)

public enum WireColor: String, Codable, CaseIterable, Sendable {
    case yellowGreen // PE — ყვითელ-მწვანე
    case blue        // N — ლურჯი
    case brown       // L / L1 — ყავისფერი
    case black       // L2 — შავი
    case grey        // L3 — ნაცრისფერი

    /// მოცემული გამტარისთვის სტანდარტული ფერი.
    public static func standard(for conductor: Conductor) -> WireColor {
        switch conductor {
        case .PE: return .yellowGreen
        case .N:  return .blue
        case .L, .L1: return .brown
        case .L2: return .black
        case .L3: return .grey
        }
    }

    /// ქართული სახელი (UI-სთვის).
    public var georgianName: String {
        switch self {
        case .yellowGreen: return "ყვითელ-მწვანე"
        case .blue:        return "ლურჯი"
        case .brown:       return "ყავისფერი"
        case .black:       return "შავი"
        case .grey:        return "ნაცრისფერი"
        }
    }
}

// MARK: - Breaker curve

public enum BreakerCurve: String, Codable, CaseIterable, Sendable {
    case B // საყოფაცხოვრებო, 3–5×In
    case C // ინდუქციური/მოტორი, 5–10×In
}

// MARK: - Component kind

public enum ComponentKind: String, Codable, CaseIterable, Sendable {
    case supply      // შემომავალი კვება (PEN → PE + N)
    case mainSwitch  // მთავარი ამომრთველი
    case spd         // ზეძაბვის დამცავი
    case rcd         // დიფ. ამომრთველი (30mA)
    case rcbo        // RCBO (MCB + RCD ერთ მოდულში)
    case mcb         // ავტომატური ამომრთველი
    case busbar      // სავარცხელი ზოლი / ნულის ან მიწის ზოლი (კონექტორი)
    case lamp        // განათება
    case socket      // როზეტი
    case motor       // მოტორი (3 ფაზა)

    /// კონექტორია? (ყველა ფეხი ერთ კვანძში ერთიანდება)
    public var isConnector: Bool { self == .busbar }

    /// დატვირთვაა?
    public var isLoad: Bool { self == .lamp || self == .socket || self == .motor }

    /// მიმდევრობითი (series) დამცავი მოწყობილობაა, რომელიც დენს ატარებს?
    public var isSeriesDevice: Bool {
        self == .mainSwitch || self == .mcb || self == .rcbo || self == .rcd
    }
}

// MARK: - Port (ფეხი / terminal)

public enum PortSide: String, Codable, Sendable {
    case input   // შემავალი მხარე (კვების მხრიდან)
    case output  // გამავალი მხარე (დატვირთვის მხრიდან)
    case single  // ცალმხრივი (დატვირთვის ფეხი, შემომავალი წყარო)
}

/// კომპონენტის ერთი ფეხი — გრაფში ეს არის node (terminal).
public struct Port: Identifiable, Hashable, Codable, Sendable {
    public let id: String
    public let conductor: Conductor
    public let side: PortSide
    public let name: String   // მოკლე იარლიყი, მაგ. "L IN"

    public init(id: String, conductor: Conductor, side: PortSide, name: String) {
        self.id = id
        self.conductor = conductor
        self.side = side
        self.name = name
    }
}

// MARK: - Component

public struct Component: Identifiable, Hashable, Codable, Sendable {
    public let id: String
    public var kind: ComponentKind
    public var name: String          // ქართული დასახელება
    public var poles: Int
    public var ratingA: Double?      // ავტომატის ნომინალი (A)
    public var curve: BreakerCurve?  // ავტომატის მახასიათებელი
    public var mAtrip: Double?       // RCD-ის გამშვები დენი (mA)
    public var powerW: Double?       // დატვირთვის სიმძლავრე (W)
    public var requiresPE: Bool      // ესაჭიროება დამცავი მიწა?
    public var leakageMa: Double?    // დეფექტი: გაჟონვის დენი (mA)
    public var faultShortToN: Bool   // დეფექტი: შიდა მოკლე ჩართვა L→N
    public var ports: [Port]

    public init(id: String,
                kind: ComponentKind,
                name: String,
                poles: Int = 1,
                ratingA: Double? = nil,
                curve: BreakerCurve? = nil,
                mAtrip: Double? = nil,
                powerW: Double? = nil,
                requiresPE: Bool = false,
                leakageMa: Double? = nil,
                faultShortToN: Bool = false,
                ports: [Port]) {
        self.id = id
        self.kind = kind
        self.name = name
        self.poles = poles
        self.ratingA = ratingA
        self.curve = curve
        self.mAtrip = mAtrip
        self.powerW = powerW
        self.requiresPE = requiresPE
        self.leakageMa = leakageMa
        self.faultShortToN = faultShortToN
        self.ports = ports
    }

    public func port(side: PortSide, conductor: Conductor) -> Port? {
        ports.first { $0.side == side && $0.conductor == conductor }
    }

    public func port(conductor: Conductor) -> Port? {
        ports.first { $0.conductor == conductor }
    }
}

// MARK: - Component factory (სტანდარტული კომპონენტები)

public enum ComponentFactory {
    private static func pid(_ comp: String, _ suffix: String) -> String { "\(comp).\(suffix)" }

    public static func supply(id: String = "supply", phase: Phase = .single) -> Component {
        var ports: [Port] = []
        if phase == .single {
            ports.append(Port(id: pid(id, "L"), conductor: .L, side: .output, name: "L"))
        } else {
            ports.append(Port(id: pid(id, "L1"), conductor: .L1, side: .output, name: "L1"))
            ports.append(Port(id: pid(id, "L2"), conductor: .L2, side: .output, name: "L2"))
            ports.append(Port(id: pid(id, "L3"), conductor: .L3, side: .output, name: "L3"))
        }
        ports.append(Port(id: pid(id, "N"), conductor: .N, side: .output, name: "N"))
        ports.append(Port(id: pid(id, "PE"), conductor: .PE, side: .output, name: "PE"))
        return Component(id: id, kind: .supply,
                         name: phase == .single ? "კვება 230V" : "კვება 400V",
                         poles: phase == .single ? 1 : 3, ports: ports)
    }

    public static func mainSwitch(id: String, phase: Phase = .single) -> Component {
        var ports: [Port] = []
        let hots: [Conductor] = phase == .single ? [.L] : [.L1, .L2, .L3]
        for c in hots {
            ports.append(Port(id: pid(id, "\(c.rawValue)in"), conductor: c, side: .input, name: "\(c.rawValue) IN"))
            ports.append(Port(id: pid(id, "\(c.rawValue)out"), conductor: c, side: .output, name: "\(c.rawValue) OUT"))
        }
        ports.append(Port(id: pid(id, "Nin"), conductor: .N, side: .input, name: "N IN"))
        ports.append(Port(id: pid(id, "Nout"), conductor: .N, side: .output, name: "N OUT"))
        let poles = phase == .single ? 2 : 4
        return Component(id: id, kind: .mainSwitch,
                         name: "მთავარი ამომრთველი \(poles)P", poles: poles, ports: ports)
    }

    public static func mcb(id: String, ratingA: Double, curve: BreakerCurve = .B, conductor: Conductor = .L) -> Component {
        let ports = [
            Port(id: pid(id, "in"), conductor: conductor, side: .input, name: "IN"),
            Port(id: pid(id, "out"), conductor: conductor, side: .output, name: "OUT")
        ]
        return Component(id: id, kind: .mcb,
                         name: "ავტომატი \(curve.rawValue)\(Int(ratingA))",
                         poles: 1, ratingA: ratingA, curve: curve, ports: ports)
    }

    public static func rcbo(id: String, ratingA: Double, curve: BreakerCurve = .B, mAtrip: Double = Electrical.standardRCDmA) -> Component {
        let ports = [
            Port(id: pid(id, "in"), conductor: .L, side: .input, name: "L IN"),
            Port(id: pid(id, "out"), conductor: .L, side: .output, name: "L OUT")
        ]
        return Component(id: id, kind: .rcbo,
                         name: "RCBO \(curve.rawValue)\(Int(ratingA)) \(Int(mAtrip))mA",
                         poles: 1, ratingA: ratingA, curve: curve, mAtrip: mAtrip, ports: ports)
    }

    public static func rcd(id: String, ratingA: Double = 40, mAtrip: Double = Electrical.standardRCDmA) -> Component {
        let ports = [
            Port(id: pid(id, "Lin"), conductor: .L, side: .input, name: "L IN"),
            Port(id: pid(id, "Lout"), conductor: .L, side: .output, name: "L OUT"),
            Port(id: pid(id, "Nin"), conductor: .N, side: .input, name: "N IN"),
            Port(id: pid(id, "Nout"), conductor: .N, side: .output, name: "N OUT")
        ]
        return Component(id: id, kind: .rcd,
                         name: "RCD \(Int(ratingA))A \(Int(mAtrip))mA",
                         poles: 2, ratingA: ratingA, mAtrip: mAtrip, ports: ports)
    }

    public static func spd(id: String) -> Component {
        let ports = [
            Port(id: pid(id, "L"), conductor: .L, side: .input, name: "L"),
            Port(id: pid(id, "PE"), conductor: .PE, side: .input, name: "PE")
        ]
        return Component(id: id, kind: .spd, name: "SPD ზეძაბვის დამცავი", poles: 1, ports: ports)
    }

    /// ზოლი (busbar / ნულის ან მიწის შინა). `slots` ფეხი, ყველა ერთ კვანძში.
    public static func busbar(id: String, conductor: Conductor, slots: Int) -> Component {
        let ports = (0..<slots).map {
            Port(id: pid(id, "\($0)"), conductor: conductor, side: .single, name: "\(conductor.rawValue)\($0)")
        }
        let title: String
        switch conductor {
        case .N: title = "ნულის ზოლი"
        case .PE: title = "მიწის ზოლი"
        default: title = "სავარცხელი ზოლი"
        }
        return Component(id: id, kind: .busbar, name: title, poles: slots, ports: ports)
    }

    public static func lamp(id: String, powerW: Double = 60, requiresPE: Bool = true, leakageMa: Double? = nil) -> Component {
        let ports = [
            Port(id: pid(id, "L"), conductor: .L, side: .single, name: "L"),
            Port(id: pid(id, "N"), conductor: .N, side: .single, name: "N"),
            Port(id: pid(id, "PE"), conductor: .PE, side: .single, name: "PE")
        ]
        return Component(id: id, kind: .lamp, name: "ნათურა \(Int(powerW))W",
                         poles: 1, powerW: powerW, requiresPE: requiresPE, leakageMa: leakageMa, ports: ports)
    }

    public static func motor(id: String, powerW: Double = 4000) -> Component {
        let ports = [
            Port(id: pid(id, "L1"), conductor: .L1, side: .single, name: "L1"),
            Port(id: pid(id, "L2"), conductor: .L2, side: .single, name: "L2"),
            Port(id: pid(id, "L3"), conductor: .L3, side: .single, name: "L3"),
            Port(id: pid(id, "N"), conductor: .N, side: .single, name: "N"),
            Port(id: pid(id, "PE"), conductor: .PE, side: .single, name: "PE")
        ]
        return Component(id: id, kind: .motor, name: "3-ფაზიანი მოტორი \(Int(powerW))W",
                         poles: 3, powerW: powerW, requiresPE: true, ports: ports)
    }

    public static func socket(id: String, powerW: Double = 2300, leakageMa: Double? = nil) -> Component {
        let ports = [
            Port(id: pid(id, "L"), conductor: .L, side: .single, name: "L"),
            Port(id: pid(id, "N"), conductor: .N, side: .single, name: "N"),
            Port(id: pid(id, "PE"), conductor: .PE, side: .single, name: "PE")
        ]
        return Component(id: id, kind: .socket, name: "როზეტი",
                         poles: 1, powerW: powerW, requiresPE: true, leakageMa: leakageMa, ports: ports)
    }
}

// MARK: - Wire

public struct Wire: Identifiable, Hashable, Codable, Sendable {
    public let id: String
    public var fromPortID: String
    public var toPortID: String
    public var csaMm2: Double
    public var color: WireColor

    public init(id: String = UUID().uuidString,
                from: String, to: String,
                csaMm2: Double, color: WireColor) {
        self.id = id
        self.fromPortID = from
        self.toPortID = to
        self.csaMm2 = csaMm2
        self.color = color
    }
}

// MARK: - Phase

public enum Phase: String, Codable, Sendable {
    case single
    case three
}

// MARK: - Board

/// მთლიანი ფარის მდგომარეობა — კომპონენტები + სადენები.
public struct Board: Codable, Sendable {
    public var phase: Phase
    public var components: [Component]
    public var wires: [Wire]

    public init(phase: Phase = .single, components: [Component] = [], wires: [Wire] = []) {
        self.phase = phase
        self.components = components
        self.wires = wires
    }

    public mutating func add(_ component: Component) { components.append(component) }

    public mutating func connect(_ a: String, _ b: String, csaMm2: Double, color: WireColor) {
        wires.append(Wire(from: a, to: b, csaMm2: csaMm2, color: color))
    }

    public func component(withPort portID: String) -> Component? {
        components.first { $0.ports.contains { $0.id == portID } }
    }

    public func port(_ portID: String) -> Port? {
        for c in components {
            if let p = c.ports.first(where: { $0.id == portID }) { return p }
        }
        return nil
    }

    public var supply: Component? { components.first { $0.kind == .supply } }
}

// MARK: - Ampacity (კაბელის კვეთა → მაქს. ავტომატი)

public enum Ampacity {
    /// კვეთა (mm²) → დასაშვები მაქსიმალური ავტომატის ნომინალი (A).
    /// თამაშის გამარტივებული წესი (იხ. სპეციფიკაცია).
    public static let table: [(csa: Double, maxA: Double)] = [
        (1.5, 16), (2.5, 20), (4, 25), (6, 32), (10, 40)
    ]

    public static func maxBreaker(forCsa csa: Double) -> Double {
        // ზუსტი დამთხვევა, ან უახლოესი ქვედა კვეთა.
        let sorted = table.sorted { $0.csa < $1.csa }
        var result = sorted.first?.maxA ?? 0
        for row in sorted where csa >= row.csa - 0.001 {
            result = row.maxA
        }
        return result
    }
}
